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
    <title>Manage Users - Training Lab Schedule</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        /* Dropdown Menu Styles */
        .dropdown-menu {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            color: #6b7280;
            border-radius: 6px;
            transition: all 0.2s;
            line-height: 1;
        }
        
        .dropdown-toggle:hover {
            background: #f3f4f6;
            color: #374151;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            bottom: 100%;
            background: white;
            min-width: 180px;
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.15);
            border-radius: 8px;
            z-index: 1000;
            margin-bottom: 0.5rem;
            overflow: visible;
        }
        
        .dropdown-content.show {
            display: block;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            color: #374151;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.15s;
            border: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            white-space: nowrap;
        }
        
        .dropdown-item:first-child {
            border-radius: 8px 8px 0 0;
        }
        
        .dropdown-item:last-child {
            border-radius: 0 0 8px 8px;
        }
        
        .dropdown-item:hover {
            background: #f3f4f6;
        }
        
        .dropdown-item.edit {
            color: #f59e0b;
        }
        
        .dropdown-item.edit:hover {
            background: #fef3c7;
        }
        
        .dropdown-item.activate {
            color: #10b981;
        }
        
        .dropdown-item.activate:hover {
            background: #d1fae5;
        }
        
        .dropdown-item.deactivate {
            color: #ef4444;
        }
        
        .dropdown-item.deactivate:hover {
            background: #fee2e2;
        }
        
        .dropdown-item.delete {
            color: #dc2626;
            border-top: 1px solid #e5e7eb;
        }
        
        .dropdown-item.delete:hover {
            background: #fee2e2;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 0;
        }
        
        /* Ensure table cells don't clip dropdown */
        .schedule-table td {
            position: relative;
            overflow: visible;
        }
        
        .table-responsive {
            overflow: visible;
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
            
            <div class="sidebar-user">
                <div class="sidebar-user-info">
                    <div class="sidebar-user-avatar">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </div>
                    <div class="sidebar-user-details">
                        <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <div class="sidebar-user-role">Super Admin</div>
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
                    <a href="../index.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">📅</span>
                        <span class="sidebar-nav-text">View Calendar</span>
                    </a>
                </div>
                
                <div class="sidebar-nav-section">
                    <div class="sidebar-nav-title">Administration</div>
                    <a href="manage_users.php" class="sidebar-nav-item active">
                        <span class="sidebar-nav-icon">👥</span>
                        <span class="sidebar-nav-text">Manage Users</span>
                    </a>
                    <a href="create_user.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon">➕</span>
                        <span class="sidebar-nav-text">Create User</span>
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
                    <h1 class="page-title">User Management</h1>
                </div>
                <div class="top-header-right">
                    <a href="create_user.php" class="btn btn-success btn-sm">+ Create User</a>
                </div>
            </header>
            
            <main class="content-wrapper">
                <div class="content-card">
                    <div class="content-card-header">
                        <h3 class="content-card-title">All Users (<?php echo $users->num_rows; ?>)</h3>
                    </div>
                    <div class="content-card-body">

                        <?php if ($users->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="schedule-table">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Email</th>
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
                                                <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
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
                                                    <?php if ($row['user_id'] != $_SESSION['user_id']): ?>
                                                        <div class="dropdown-menu">
                                                            <button class="dropdown-toggle" onclick="toggleDropdown(event, <?php echo $row['user_id']; ?>)">⋮</button>
                                                            <div class="dropdown-content" id="dropdown-<?php echo $row['user_id']; ?>">
                                                                <a href="edit_user.php?id=<?php echo $row['user_id']; ?>" class="dropdown-item edit">
                                                                    <span>✏️</span> Edit
                                                                </a>
                                                                <?php if ($row['status'] === 'active'): ?>
                                                                    <a href="toggle_user_status.php?id=<?php echo $row['user_id']; ?>&action=deactivate" 
                                                                       class="dropdown-item deactivate" 
                                                                       onclick="return confirm('Deactivate this user?');">
                                                                        <span>🚫</span> Deactivate
                                                                    </a>
                                                                <?php else: ?>
                                                                    <a href="toggle_user_status.php?id=<?php echo $row['user_id']; ?>&action=activate" 
                                                                       class="dropdown-item activate" 
                                                                       onclick="return confirm('Activate this user?');">
                                                                        <span>✅</span> Activate
                                                                    </a>
                                                                <?php endif; ?>
                                                                <div class="dropdown-divider"></div>
                                                                <a href="delete_user.php?id=<?php echo $row['user_id']; ?>" 
                                                                   class="dropdown-item delete" 
                                                                   onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                                    <span>🗑️</span> Delete
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <span style="color: #9ca3af; font-size: 0.875rem;">Current User</span>
                                                    <?php endif; ?>
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
                </div>
            </main>
        </div>
    </div>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay"></div>
    
    <script src="../assets/js/sidebar.js"></script>
    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle dropdown menu
            window.toggleDropdown = function(event, userId) {
                event.stopPropagation();
                const dropdown = document.getElementById('dropdown-' + userId);
                const allDropdowns = document.querySelectorAll('.dropdown-content');
                
                // Close all other dropdowns
                allDropdowns.forEach(d => {
                    if (d !== dropdown) {
                        d.classList.remove('show');
                    }
                });
                
                // Toggle current dropdown
                dropdown.classList.toggle('show');
            };
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.matches('.dropdown-toggle')) {
                    const dropdowns = document.querySelectorAll('.dropdown-content');
                    dropdowns.forEach(dropdown => {
                        dropdown.classList.remove('show');
                    });
                }
            });
        });
    </script>
</body>
</html>

<?php
closeDBConnection($conn);
?>
