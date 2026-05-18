<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireAnyRole(['admin', 'superadmin']);

// Flash messages
$flash_message = '';
$flash_type    = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type    = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$conn = getDBConnection();
$approved_schedules = $conn->query("SELECT * FROM approved_schedules ORDER BY start_date DESC, start_time DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules - Training Lab Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        /* ── Flash Alert ── */
        .flash-alert {
            display: flex; align-items: center; gap: .75rem;
            padding: 1rem 1.25rem; border-radius: 10px;
            margin: 1rem 1.5rem 0; font-weight: 500; font-size: .95rem;
            animation: slideDown .4s ease;
        }
        @keyframes slideDown { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
        .flash-success { background:#d4edda; color:#155724; border-left:4px solid #28a745; }
        .flash-error   { background:#f8d7da; color:#721c24; border-left:4px solid #dc3545; }

        /* ── Clickable Row ── */
        .schedule-table tbody tr {
            cursor: pointer;
            transition: background .18s, transform .18s;
        }
        .schedule-table tbody tr:hover {
            background: #e8f5e9;
            transform: translateX(3px);
        }
        .row-hint {
            display: inline-flex; align-items: center; gap: .35rem;
            font-size: .75rem; color: #4CAF50; font-weight: 600;
            background: #e8f5e9; padding: .25rem .6rem; border-radius: 20px;
        }

        /* ── Modal Overlay ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.55); backdrop-filter: blur(4px);
            z-index: 2000; align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }

        /* ── Modal Container ── */
        .modal-box {
            background: #fff; border-radius: 18px;
            width: 90%; max-width: 520px;
            box-shadow: 0 24px 64px rgba(0,0,0,.25);
            overflow: hidden; position: relative;
            animation: modalIn .3s ease-out;
        }
        @keyframes modalIn { 
            from { 
                opacity: 0; 
                transform: scale(0.95) translateY(-10px); 
            } 
            to { 
                opacity: 1; 
                transform: scale(1) translateY(0); 
            } 
        }

        /* ── Modal Views ── */
        .modal-view { display: none; flex-direction: column; }
        .modal-view.active { display: flex; animation: fadeSlide .25s ease; }
        @keyframes fadeSlide { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }

        /* ── Modal Header ── */
        .modal-header {
            padding: 1.5rem 1.75rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-header-title {
            font-size: 1.2rem; font-weight: 700; color: #1e3a5f;
            display: flex; align-items: center; gap: .5rem;
        }
        .modal-close {
            background: none; border: none; font-size: 1.5rem; cursor: pointer;
            color: #9ca3af; line-height: 1; padding: .25rem;
            border-radius: 6px; transition: color .2s, background .2s;
        }
        .modal-close:hover { color: #374151; background: #f3f4f6; }

        /* ── Modal Body ── */
        .modal-body { padding: 1.25rem 1.75rem; flex: 1; }

        /* ── Detail Grid ── */
        .detail-grid { display: grid; gap: .75rem; }
        .detail-item {
            display: grid; grid-template-columns: 130px 1fr;
            align-items: start; gap: .5rem; padding: .6rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .detail-item:last-child { border-bottom: none; }
        .detail-label { font-size: .8rem; font-weight: 700; color: #4CAF50; text-transform: uppercase; letter-spacing: .04em; }
        .detail-value { font-size: .95rem; color: #374151; font-weight: 500; }

        /* ── Warning Box ── */
        .warning-box {
            background: #fff8e1; border: 1px solid #fbbf24; border-radius: 10px;
            padding: 1rem 1.25rem; display: flex; gap: .75rem; align-items: flex-start;
            margin-bottom: 1rem;
        }
        .warning-box-icon { font-size: 1.5rem; line-height: 1; }
        .warning-box-text { font-size: .9rem; color: #92400e; line-height: 1.5; }
        .warning-box-title { font-weight: 700; margin-bottom: .25rem; font-size: .95rem; }

        /* ── Form Elements in Modal ── */
        .modal-form-group { margin-bottom: 1.1rem; }
        .modal-form-group label {
            display: block; font-size: .82rem; font-weight: 700; color: #374151;
            margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .04em;
        }
        .modal-form-group input,
        .modal-form-group textarea {
            width: 100%; padding: .65rem .9rem; border: 2px solid #e5e7eb;
            border-radius: 8px; font-size: .92rem; font-family: inherit;
            transition: border-color .2s;
        }
        .modal-form-group input:focus,
        .modal-form-group textarea:focus { outline: none; border-color: #4CAF50; }
        .modal-form-group textarea { resize: vertical; min-height: 72px; }
        .time-row { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .current-info {
            background: #f8f9fa; border-radius: 8px; padding: .75rem 1rem;
            font-size: .82rem; color: #6b7280; margin-bottom: 1rem;
            border-left: 3px solid #4CAF50;
        }
        .current-info strong { color: #374151; }

        /* ── Modal Footer ── */
        .modal-footer {
            padding: 1rem 1.75rem 1.5rem;
            display: flex; gap: .75rem; justify-content: flex-end;
            border-top: 1px solid #f0f0f0;
        }

        /* ── Modal Buttons ── */
        .btn-modal-back {
            padding: .65rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 9px;
            background: #fff; color: #6b7280; font-size: .9rem; font-weight: 600;
            cursor: pointer; transition: all .2s; display: flex; align-items: center; gap: .4rem;
        }
        .btn-modal-back:hover { border-color: #9ca3af; color: #374151; }

        .btn-pullout {
            padding: .65rem 1.25rem; border: none; border-radius: 9px;
            background: linear-gradient(135deg, #ff5722, #e53935);
            color: #fff; font-size: .9rem; font-weight: 600; cursor: pointer;
            transition: all .2s; display: flex; align-items: center; gap: .4rem;
            box-shadow: 0 4px 12px rgba(229,57,53,.3);
        }
        .btn-pullout:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(229,57,53,.4); }

        .btn-reschedule {
            padding: .65rem 1.25rem; border: none; border-radius: 9px;
            background: linear-gradient(135deg, #1e3a5f, #2e5984);
            color: #fff; font-size: .9rem; font-weight: 600; cursor: pointer;
            transition: all .2s; display: flex; align-items: center; gap: .4rem;
            box-shadow: 0 4px 12px rgba(30,58,95,.25);
        }
        .btn-reschedule:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(30,58,95,.35); }

        .btn-confirm-pullout {
            padding: .65rem 1.25rem; border: none; border-radius: 9px;
            background: linear-gradient(135deg, #ff5722, #e53935);
            color: #fff; font-size: .9rem; font-weight: 600; cursor: pointer;
            transition: all .2s; display: flex; align-items: center; gap: .4rem;
            box-shadow: 0 4px 12px rgba(229,57,53,.3);
        }
        .btn-confirm-pullout:hover { transform: translateY(-1px); }

        .btn-confirm-reschedule {
            padding: .65rem 1.25rem; border: none; border-radius: 9px;
            background: linear-gradient(135deg, #4CAF50, #43a047);
            color: #fff; font-size: .9rem; font-weight: 600; cursor: pointer;
            transition: all .2s; display: flex; align-items: center; gap: .4rem;
            box-shadow: 0 4px 12px rgba(76,175,80,.3);
        }
        .btn-confirm-reschedule:hover { transform: translateY(-1px); }

        /* ── Empty State ── */
        .empty-state {
            text-align: center; padding: 3.5rem 2rem; color: #9ca3af;
        }
        .empty-state-icon { font-size: 3.5rem; margin-bottom: 1rem; }
        .empty-state-text { font-size: 1.05rem; }

        /* header avatar */
        .header-profile-link { display:flex; align-items:center; gap:.75rem; padding:.5rem 1rem; border-radius:10px; }
        .header-user-avatar { width:40px; height:40px; background:linear-gradient(135deg,#4CAF50,#66bb6a); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; color:#fff; }
        .header-user-name { font-size:1rem; font-weight:600; color:#1e3a5f; }

        /* ── Add Walk-in button ── */
        .btn-add-walkin {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .6rem 1.2rem; border: none; border-radius: 10px;
            background: linear-gradient(135deg, #4CAF50, #43a047);
            color: #fff; font-size: .88rem; font-weight: 700; cursor: pointer;
            transition: all .2s; box-shadow: 0 4px 12px rgba(76,175,80,.3);
            text-decoration: none;
        }
        .btn-add-walkin:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(76,175,80,.45); }

        /* ── Add-schedule modal form ── */
        .add-modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.55); backdrop-filter: blur(4px);
            z-index: 3000; align-items: center; justify-content: center;
        }
        .add-modal-overlay.open { display: flex; }
        .add-modal-box {
            background: #fff; border-radius: 18px; width: 90%; max-width: 560px;
            box-shadow: 0 24px 64px rgba(0,0,0,.25); overflow: hidden;
            animation: modalFadeIn .25s ease-out;
            max-height: 90vh; overflow-y: auto;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .add-modal-header {
            padding: 1.5rem 1.75rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; background: #fff; z-index: 1;
        }
        .add-modal-title {
            font-size: 1.2rem; font-weight: 700; color: #1e3a5f;
            display: flex; align-items: center; gap: .5rem;
        }
        .add-modal-body { padding: 1.25rem 1.75rem; }
        .add-modal-footer {
            padding: 1rem 1.75rem 1.5rem;
            display: flex; gap: .75rem; justify-content: flex-end;
            border-top: 1px solid #f0f0f0;
            position: sticky; bottom: 0; background: #fff;
        }
        .add-form-group { margin-bottom: 1.1rem; }
        .add-form-group label {
            display: block; font-size: .82rem; font-weight: 700;
            color: #374151; margin-bottom: .4rem;
            text-transform: uppercase; letter-spacing: .04em;
        }
        .add-form-group input, .add-form-group textarea {
            width: 100%; padding: .65rem .9rem;
            border: 2px solid #e5e7eb; border-radius: 8px;
            font-size: .92rem; font-family: inherit; transition: border-color .2s;
            box-sizing: border-box;
        }
        .add-form-group input:focus, .add-form-group textarea:focus {
            outline: none; border-color: #4CAF50;
        }
        .add-form-group textarea { resize: vertical; min-height: 72px; }
        .add-time-row { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .add-walkin-note {
            background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 10px;
            padding: .85rem 1.1rem; margin-bottom: 1.25rem;
            font-size: .85rem; color: #2e7d32; display: flex; gap: .6rem; align-items: flex-start;
        }
        .btn-add-submit {
            padding: .7rem 1.5rem; border: none; border-radius: 9px;
            background: linear-gradient(135deg, #4CAF50, #43a047);
            color: #fff; font-size: .92rem; font-weight: 700; cursor: pointer;
            transition: all .2s; box-shadow: 0 4px 12px rgba(76,175,80,.3);
        }
        .btn-add-submit:hover { transform: translateY(-1px); }
        .btn-add-cancel {
            padding: .7rem 1.25rem; border: 2px solid #e5e7eb; border-radius: 9px;
            background: #fff; color: #6b7280; font-size: .92rem; font-weight: 600;
            cursor: pointer; transition: all .2s;
        }
        .btn-add-cancel:hover { border-color: #9ca3af; color: #374151; }
    </style>
</head>
<body>
<div class="app-wrapper">

    <!-- ═══ SIDEBAR ═══ -->
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

        <div class="sidebar-user">
            <div class="sidebar-user-info">
                <div class="sidebar-user-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                <div class="sidebar-user-details">
                    <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="sidebar-user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-nav-section">
                <div class="sidebar-nav-title">Main Menu</div>
                <a href="dashboard.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon">📊</span>
                    <span class="sidebar-nav-text">Dashboard</span>
                </a>
                <a href="approved_schedules.php" class="sidebar-nav-item active">
                    <span class="sidebar-nav-icon">✅</span>
                    <span class="sidebar-nav-text">Manage Schedules</span>
                </a>
                <a href="cancellation_requests.php" class="sidebar-nav-item">
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

    <!-- ═══ MAIN CONTENT ═══ -->
    <div class="main-content">

        <header class="top-header">
            <div class="top-header-left">
                <button class="menu-toggle">☰</button>
                <div class="header-profile-link">
                    <div class="header-user-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                    <span class="header-user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </div>
            <div class="top-header-right">
                <span style="color:#6b7280; font-size:.9rem;"><?php echo date('l, F d, Y'); ?></span>
            </div>
        </header>

        <!-- Flash -->
        <?php if ($flash_message): ?>
        <div class="flash-alert flash-<?php echo htmlspecialchars($flash_type); ?>" id="flashAlert">
            <?php echo $flash_type === 'success' ? '✅' : '❌'; ?>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
        <?php endif; ?>

        <main class="content-wrapper">
            <div class="content-card">
                <div class="content-card-header" style="flex-wrap:wrap; gap:.75rem;">
                    <div style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;">
                        <h3 class="content-card-title">
                            ✅ Approved Schedules
                            <span style="font-size:.8rem; font-weight:500; color:#6b7280; margin-left:.5rem;">
                                (<?php echo $approved_schedules->num_rows; ?> total)
                            </span>
                        </h3>
                        <span style="font-size:.8rem; color:#4CAF50; font-weight:500;">💡 Click any row to manage</span>
                    </div>
                    <button class="btn-add-walkin" onclick="openAddModal()">
                        ➕ Add Walk-in Schedule
                    </button>
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
                                    <th>Office</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $approved_schedules->fetch_assoc()): ?>
                                <tr onclick="openModal(<?php echo $row['schedule_id']; ?>)"
                                    data-id="<?php echo $row['schedule_id']; ?>">
                                    <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                    <td>
                                        <?php echo date('h:i A', strtotime($row['start_time'])); ?> –
                                        <?php echo date('h:i A', strtotime($row['end_time'])); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['program_owner']); ?></td>
                                    <td><?php echo htmlspecialchars($row['office']); ?></td>
                                    <td><span class="row-hint">👁 View</span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📅</div>
                        <div class="empty-state-text">No approved schedules yet.</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div><!-- /main-content -->
</div><!-- /app-wrapper -->

<!-- ═══════════════════════════════════════
     SCHEDULE MODAL  (3 views)
═══════════════════════════════════════ -->
<div class="modal-overlay" id="scheduleModal" onclick="handleOverlayClick(event)">
    <div class="modal-box">

        <!-- ── VIEW 1: Details ── -->
        <div class="modal-view active" id="viewDetails">
            <div class="modal-header">
                <span class="modal-header-title">📋 Schedule Details</span>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="detail-grid" id="detailGrid">
                    <!-- populated by JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-pullout" onclick="showView('viewPullout')">
                    🗑 Pull-out Schedule
                </button>
                <button class="btn-reschedule" onclick="showView('viewReschedule')">
                    📅 Reschedule
                </button>
            </div>
        </div>

        <!-- ── VIEW 2: Pull-out Confirmation ── -->
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
                        The schedule will be permanently removed from the calendar.
                        The requestor will be notified and may resubmit a new request.
                    </div>
                </div>
                <p style="font-size:.9rem; color:#374151; margin-bottom:1rem;">
                    Pulling out: <strong id="pulloutTitle" style="color:#1e3a5f;"></strong>
                </p>
                <div class="modal-form-group">
                    <label>Reason for Pull-out <span style="color:#9ca3af; font-weight:400;">(optional)</span></label>
                    <textarea id="pulloutReason" placeholder="e.g. Venue unavailable, policy change..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-back" onclick="showView('viewDetails')">← Back</button>
                <button class="btn-confirm-pullout" onclick="submitPullout()">🗑 Confirm Pull-out</button>
            </div>
        </div>

        <!-- ── VIEW 3: Reschedule Form ── -->
        <div class="modal-view" id="viewReschedule">
            <div class="modal-header">
                <span class="modal-header-title" style="color:#1e3a5f;">📅 Reschedule</span>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="current-info" id="currentInfo"></div>
                <form id="rescheduleForm" method="POST" action="reschedule.php">
                    <input type="hidden" name="schedule_id" id="rescheduleId">
                    <div class="modal-form-group">
                        <label>New Date *</label>
                        <input type="date" name="new_date" id="newDate"
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="time-row">
                        <div class="modal-form-group">
                            <label>New Start Time *</label>
                            <input type="time" name="new_start_time" id="newStartTime" required>
                        </div>
                        <div class="modal-form-group">
                            <label>New End Time *</label>
                            <input type="time" name="new_end_time" id="newEndTime" required>
                        </div>
                    </div>
                    <div class="modal-form-group">
                        <label>Reason for Rescheduling <span style="color:#9ca3af; font-weight:400;">(optional)</span></label>
                        <textarea name="reason" placeholder="e.g. Conflict with another event..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-back" onclick="showView('viewDetails')">← Back</button>
                <button class="btn-confirm-reschedule" onclick="submitReschedule()">✓ Confirm Reschedule</button>
            </div>
        </div>

    </div><!-- /modal-box -->
</div><!-- /modal-overlay -->

<!-- Hidden pull-out form -->
<form id="pulloutForm" method="POST" action="pullout_schedule.php" style="display:none;">
    <input type="hidden" name="schedule_id" id="pulloutScheduleId">
    <input type="hidden" name="reason"      id="pulloutReasonHidden">
</form>

<!-- Sidebar overlay -->
<div class="sidebar-overlay"></div>
<script src="../assets/js/sidebar.js"></script>

<script>
    let activeScheduleId = null;

    /* ── Open modal: fetch details ── */
    function openModal(scheduleId) {
        activeScheduleId = scheduleId;
        showView('viewDetails');

        // Reset detail grid
        document.getElementById('detailGrid').innerHTML =
            '<div style="text-align:center;padding:2rem;color:#9ca3af;">Loading…</div>';

        document.getElementById('scheduleModal').classList.add('open');
        document.body.style.overflow = 'hidden';

        fetch(`get_schedule_details.php?id=${scheduleId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) { closeModal(); return; }
                const s = data.schedule;

                // Populate details view
                document.getElementById('detailGrid').innerHTML = `
                    <div class="detail-item">
                        <span class="detail-label">📋 Title</span>
                        <span class="detail-value">${escHtml(s.title)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">📅 Date</span>
                        <span class="detail-value">${formatDate(s.start_date)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">🕐 Time</span>
                        <span class="detail-value">${formatTime(s.start_time)} – ${formatTime(s.end_time)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">👥 Participants</span>
                        <span class="detail-value">${escHtml(s.participants)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">🏢 Program Owner</span>
                        <span class="detail-value">${escHtml(s.program_owner)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">🏬 Office</span>
                        <span class="detail-value">${escHtml(s.office)}</span>
                    </div>
                `;

                // Pre-fill pull-out view
                document.getElementById('pulloutTitle').textContent = s.title;
                document.getElementById('pulloutScheduleId').value  = scheduleId;
                document.getElementById('pulloutReason').value      = '';

                // Pre-fill reschedule view
                document.getElementById('rescheduleId').value    = scheduleId;
                document.getElementById('newDate').value         = s.start_date;
                document.getElementById('newStartTime').value    = s.start_time.substring(0,5);
                document.getElementById('newEndTime').value      = s.end_time.substring(0,5);
                document.getElementById('currentInfo').innerHTML =
                    `<strong>Current:</strong> ${formatDate(s.start_date)}, 
                     ${formatTime(s.start_time)} – ${formatTime(s.end_time)}`;
            })
            .catch(() => closeModal());
    }

    /* ── Switch view ── */
    function showView(viewId) {
        document.querySelectorAll('.modal-view').forEach(v => v.classList.remove('active'));
        document.getElementById(viewId).classList.add('active');
    }

    /* ── Close modal ── */
    function closeModal() {
        document.getElementById('scheduleModal').classList.remove('open');
        document.body.style.overflow = '';
        activeScheduleId = null;
    }

    /* Close on overlay click (not modal-box) */
    function handleOverlayClick(e) {
        if (e.target === document.getElementById('scheduleModal')) closeModal();
    }

    /* Close on Escape */
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    /* ── Submit pull-out ── */
    function submitPullout() {
        document.getElementById('pulloutReasonHidden').value = document.getElementById('pulloutReason').value;
        document.getElementById('pulloutForm').submit();
    }

    /* ── Submit reschedule ── */
    function submitReschedule() {
        document.getElementById('rescheduleForm').submit();
    }

    /* ── Helpers ── */
    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });
    }

    function formatTime(timeStr) {
        const [h, m] = timeStr.split(':');
        const date = new Date();
        date.setHours(parseInt(h), parseInt(m));
        return date.toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit', hour12:true });
    }

    /* Auto-hide flash */
    const flash = document.getElementById('flashAlert');
    if (flash) setTimeout(() => {
        flash.style.transition = 'opacity .5s';
        flash.style.opacity = '0';
        setTimeout(() => flash.remove(), 500);
    }, 4500);

    /* ── Add Walk-in Schedule Modal ── */
    function openAddModal(prefilledDate = '') {
        if (prefilledDate) {
            document.getElementById('addDate').value = prefilledDate;
        } else {
            document.getElementById('addDate').value = '';
        }
        document.getElementById('addModalOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('addTitle').focus(), 200);
    }

    function closeAddModal() {
        document.getElementById('addModalOverlay').classList.remove('open');
        document.body.style.overflow = '';
        document.getElementById('addScheduleForm').reset();
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeModal(); closeAddModal(); }
    });
    
    // Wait for DOM to be fully loaded before adding event listeners
    document.addEventListener('DOMContentLoaded', function() {
        const addModalOverlay = document.getElementById('addModalOverlay');
        if (addModalOverlay) {
            addModalOverlay.addEventListener('click', function(e) {
                if (e.target === this) closeAddModal();
            });
        }
    });
</script>

<!-- ═══ ADD WALK-IN SCHEDULE MODAL ═══ -->
<div class="add-modal-overlay" id="addModalOverlay">
    <div class="add-modal-box">
        <div class="add-modal-header">
            <span class="add-modal-title">🏃 Add Walk-in Schedule</span>
            <button class="modal-close" onclick="closeAddModal()">&times;</button>
        </div>
        <div class="add-modal-body">
            <div class="add-walkin-note">
                <span style="font-size:1.2rem;">ℹ️</span>
                <div>This schedule will be <strong>immediately approved</strong> and added to the calendar.
                Use this for walk-in requestors or urgent bookings that need no prior request.</div>
            </div>
            <form id="addScheduleForm" method="POST" action="add_schedule.php">
                <input type="hidden" name="redirect" value="approved_schedules.php">

                <div class="add-form-group">
                    <label>📅 Date *</label>
                    <input type="date" name="start_date" id="addDate"
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="add-form-group">
                    <label>📌 Title *</label>
                    <input type="text" name="title" id="addTitle" required
                           placeholder="e.g. Walk-in Training Session">
                </div>
                <div class="add-form-group">
                    <label>📧 Email *</label>
                    <input type="email" name="deped_email" required
                           placeholder="e.g. juan.delacruz@example.com">
                </div>
                <div class="add-time-row">
                    <div class="add-form-group">
                        <label>🕐 Start Time *</label>
                        <input type="time" name="start_time" id="addStartTime" required>
                    </div>
                    <div class="add-form-group">
                        <label>🕐 End Time *</label>
                        <input type="time" name="end_time" id="addEndTime" required>
                    </div>
                </div>
                <div class="add-form-group">
                    <label>👥 No. of Participants *</label>
                    <input type="number" name="participants" min="1" required
                           placeholder="e.g. 25">
                </div>
                <div class="add-form-group">
                    <label>👤 Program Owner *</label>
                    <input type="text" name="program_owner" required
                           placeholder="Name of the program owner">
                </div>
                <div class="add-form-group">
                    <label>🏢 Office *</label>
                    <input type="text" name="office" required
                           placeholder="Office or division name">
                </div>
                <div class="add-form-group">
                    <label>📝 Remarks <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                    <textarea name="remarks" placeholder="Additional notes or requirements..."></textarea>
                </div>
            </form>
        </div>
        <div class="add-modal-footer">
            <button class="btn-add-cancel" onclick="closeAddModal()">Cancel</button>
            <button class="btn-add-submit" onclick="document.getElementById('addScheduleForm').submit()">
                ✅ Add Schedule
            </button>
        </div>
    </div>
</div>

</body>
</html>


<?php closeDBConnection($conn); ?>
