<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

// Check if user is admin
checkAdmin();

// First, add is_suspended column if it doesn't exist
$check_column = "SHOW COLUMNS FROM users LIKE 'is_suspended'";
$column_exists = mysqli_query($conn, $check_column);
if(mysqli_num_rows($column_exists) == 0) {
    $alter_query = "ALTER TABLE users ADD COLUMN is_suspended BOOLEAN DEFAULT FALSE";
    mysqli_query($conn, $alter_query);
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

// Filter settings
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$role_filter = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';

// Build WHERE clause
$where = "1=1";
if($search) {
    $where .= " AND (username LIKE '%$search%' OR email LIKE '%$search%')";
}
if($role_filter) {
    $where .= " AND role = '$role_filter'";
}
if($status_filter == 'active') {
    $where .= " AND (is_suspended = 0 OR is_suspended IS NULL)";
} elseif($status_filter == 'suspended') {
    $where .= " AND is_suspended = 1";
}

// Get total users count for pagination
$count_query = "SELECT COUNT(*) as total FROM users WHERE $where";
$count_result = mysqli_query($conn, $count_query);
if(!$count_result) {
    die("Query failed: " . mysqli_error($conn));
}
$total_users = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_users / $limit);

// Get users with their statistics
$query = "SELECT 
            u.*,
            (SELECT COUNT(*) FROM lost_reports WHERE user_id = u.id) as lost_count,
            (SELECT COUNT(*) FROM found_reports WHERE user_id = u.id) as found_count,
            (SELECT COUNT(*) FROM claims WHERE claimant_id = u.id) as claims_count,
            (SELECT COUNT(*) FROM claims WHERE claimant_id = u.id AND status = 'approved') as approved_claims
          FROM users u
          WHERE $where
          ORDER BY u.created_at DESC
          LIMIT $offset, $limit";

$users = mysqli_query($conn, $query);
if(!$users) {
    die("Query failed: " . mysqli_error($conn));
}

// Get statistics for dashboard
$stats_query = "SELECT 
                  COUNT(*) as total_users,
                  SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
                  SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as total_users_count,
                  SUM(CASE WHEN is_suspended = 1 THEN 1 ELSE 0 END) as suspended_users,
                  SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_users_week
                FROM users";
$stats_result = mysqli_query($conn, $stats_query);
if(!$stats_result) {
    // If query fails, provide default stats
    $stats = [
        'total_users' => 0,
        'total_admins' => 0,
        'total_users_count' => 0,
        'suspended_users' => 0,
        'new_users_week' => 0
    ];
} else {
    $stats = mysqli_fetch_assoc($stats_result);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
            transition: all 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.1);
            padding-left: 25px;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .navbar-top {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card {
            transition: transform 0.3s;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-close-white {
            filter: brightness(0) invert(1);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0 sidebar">
                <div class="text-center py-4">
                    <i class="fas fa-search fa-3x"></i>
                    <h5 class="mt-2">Admin Panel</h5>
                    <small>Lost & Found System</small>
                </div>
                <hr class="bg-light">
                <nav class="nav flex-column">
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="approve.php">
                        <i class="fas fa-check-circle"></i> Approve Items
                    </a>
                    <a href="recyclebin.php">
                        <i class="fas fa-trash-restore"></i> Recycle Bin
                    </a>
                    <a href="handle_claims.php">
                        <i class="fas fa-handshake"></i> Manage Claims
                    </a>
                    <a href="manage_accounts.php" class="active">
                        <i class="fas fa-users-cog"></i> Manage Accounts
                    </a>
                    <hr class="bg-light">
                    <a href="../../auth/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-0 main-content">
                <!-- Top Navbar -->
                <nav class="navbar-top px-4 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-users-cog"></i> Manage User Accounts
                        </h4>
                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#profileModal">
                                    <i class="fas fa-user"></i> My Profile
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../../auth/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </nav>

                <div class="p-4">
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Total Users</h6>
                                            <h2 class="mb-0"><?php echo $stats['total_users']; ?></h2>
                                        </div>
                                        <i class="fas fa-users fa-3x opacity-50"></i>
                                    </div>
                                    <small class="mt-2 d-block">
                                        <i class="fas fa-user-plus"></i> New this week: <?php echo $stats['new_users_week']; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Active Users</h6>
                                            <h2 class="mb-0"><?php echo $stats['total_users'] - ($stats['suspended_users'] ?? 0); ?></h2>
                                        </div>
                                        <i class="fas fa-user-check fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Administrators</h6>
                                            <h2 class="mb-0"><?php echo $stats['total_admins']; ?></h2>
                                        </div>
                                        <i class="fas fa-user-shield fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-danger text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Suspended Users</h6>
                                            <h2 class="mb-0"><?php echo $stats['suspended_users'] ?? 0; ?></h2>
                                        </div>
                                        <i class="fas fa-ban fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters and Actions -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <form method="GET" class="row g-3">
                                        <div class="col-md-4">
                                            <input type="text" name="search" class="form-control" 
                                                   placeholder="Search by username or email..." 
                                                   value="<?php echo htmlspecialchars($search); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <select name="role" class="form-control">
                                                <option value="">All Roles</option>
                                                <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                <option value="user" <?php echo $role_filter == 'user' ? 'selected' : ''; ?>>User</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select name="status" class="form-control">
                                                <option value="">All Status</option>
                                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-search"></i> Filter
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                                        <i class="fas fa-user-plus"></i> Add New Admin
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if(isset($_GET['msg'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i>
                            <?php 
                                $msg = $_GET['msg'];
                                if($msg == 'user_deleted') echo "User has been deleted successfully!";
                                elseif($msg == 'admin_promoted') echo "User has been promoted to admin!";
                                elseif($msg == 'admin_removed') echo "Admin privileges have been removed!";
                                elseif($msg == 'user_suspended') echo "User has been suspended!";
                                elseif($msg == 'user_activated') echo "User has been activated!";
                                elseif($msg == 'password_reset') echo "Password has been reset to 'password123'!";
                                else echo "Operation completed successfully!";
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if(isset($_GET['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php 
                                $error = $_GET['error'];
                                if($error == 'cannot_delete_self') echo "You cannot delete your own account!";
                                elseif($error == 'cannot_remove_self_admin') echo "You cannot remove your own admin privileges!";
                                else echo "Error: " . htmlspecialchars($error);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Users Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-list"></i> User Accounts List</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>Contact</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Reports</th>
                                            <th>Claims</th>
                                            <th>Joined</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(mysqli_num_rows($users) > 0): ?>
                                            <?php while($user = mysqli_fetch_assoc($users)): ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-2">
                                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                            <br>
                                                            <small class="text-muted">ID: #<?php echo $user['id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'info'; ?>">
                                                        <i class="fas fa-<?php echo $user['role'] == 'admin' ? 'user-shield' : 'user'; ?>"></i>
                                                        <?php echo strtoupper($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if(isset($user['is_suspended']) && $user['is_suspended'] == 1): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-ban"></i> Suspended
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle"></i> Active
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <i class="fas fa-frown"></i> Lost: <?php echo $user['lost_count']; ?>
                                                    </span>
                                                    <br>
                                                    <span class="badge bg-success mt-1">
                                                        <i class="fas fa-smile"></i> Found: <?php echo $user['found_count']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-handshake"></i> Total: <?php echo $user['claims_count']; ?>
                                                    </span>
                                                    <br>
                                                    <span class="badge bg-info mt-1">
                                                        <i class="fas fa-check"></i> Approved: <?php echo $user['approved_claims']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <i class="fas fa-calendar"></i> 
                                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo date('H:i', strtotime($user['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-info" onclick="viewUser(<?php echo $user['id']; ?>)" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        
                                                        <?php if($user['id'] != $_SESSION['user_id']): ?>
                                                            <?php if($user['role'] == 'user'): ?>
                                                                <button class="btn btn-warning" onclick="makeAdmin(<?php echo $user['id']; ?>)" title="Make Admin">
                                                                    <i class="fas fa-user-shield"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-secondary" onclick="removeAdmin(<?php echo $user['id']; ?>)" title="Remove Admin">
                                                                    <i class="fas fa-user"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            
                                                            <?php if(isset($user['is_suspended']) && $user['is_suspended'] == 1): ?>
                                                                <button class="btn btn-success" onclick="activateUser(<?php echo $user['id']; ?>)" title="Activate">
                                                                    <i class="fas fa-check-circle"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button class="btn btn-warning" onclick="suspendUser(<?php echo $user['id']; ?>)" title="Suspend">
                                                                    <i class="fas fa-ban"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            
                                                            <button class="btn btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <button class="btn btn-primary" onclick="resetPassword(<?php echo $user['id']; ?>)" title="Reset Password">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center">
                                                    <div class="alert alert-info mb-0">
                                                        <i class="fas fa-info-circle"></i> No users found matching your criteria.
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>">
                                            Previous
                                        </a>
                                    </li>
                                    
                                    <?php 
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    for($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>">
                                            Next
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                            
                            <div class="text-muted mt-3">
                                <small>Showing <?php echo mysqli_num_rows($users); ?> of <?php echo $total_users; ?> users</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus"></i> Add New Admin
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addAdminForm">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                            <small class="text-muted">Password will be hashed before storing</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="addAdmin()">Add Admin</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-circle"></i> User Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // User management functions
        function deleteUser(id) {
            if(confirm('⚠️ WARNING: This will permanently delete the user and ALL their data (reports, claims, etc.). This action cannot be undone! Continue?')) {
                window.location.href = "../../Backend/Admin/manage_accounts.php?action=delete&id=" + id;
            }
        }
        
        function makeAdmin(id) {
            if(confirm('Promote this user to admin? They will have full access to the admin panel.')) {
                window.location.href = "../../Backend/Admin/manage_accounts.php?action=make_admin&id=" + id;
            }
        }
        
        function removeAdmin(id) {
            if(confirm('Remove admin privileges from this user? They will become a regular user.')) {
                window.location.href = "../../Backend/Admin/manage_accounts.php?action=remove_admin&id=" + id;
            }
        }
        
        function suspendUser(id) {
            if(confirm('Suspend this user? They will not be able to login or submit reports.')) {
                window.location.href = "../../Backend/Admin/manage_accounts.php?action=suspend&id=" + id;
            }
        }
        
        function activateUser(id) {
            if(confirm('Activate this user? They will be able to login and use the system again.')) {
                window.location.href = "../../Backend/Admin/manage_accounts.php?action=activate&id=" + id;
            }
        }
        
        function resetPassword(id) {
            if(confirm('Reset password to default "password123"? The user will need to change it on next login.')) {
                window.location.href = "../../Backend/Admin/manage_accounts.php?action=reset_password&id=" + id;
            }
        }
        
        function viewUser(id) {
            // Simple user details display
            const modalContent = document.getElementById('userDetailsContent');
            modalContent.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            // Fetch user details via AJAX
            fetch('../../Backend/Admin/manage_accounts.php?action=get_user&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        let html = `
                            <div class="row">
                                <div class="col-md-12">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="30%">User ID:</th>
                                            <td>#${data.user.id}</td>
                                        </tr>
                                        <tr>
                                            <th>Username:</th>
                                            <td>${data.user.username}</td>
                                        </tr>
                                        <tr>
                                            <th>Email:</th>
                                            <td>${data.user.email}</td>
                                        </tr>
                                        <tr>
                                            <th>Role:</th>
                                            <td><span class="badge bg-${data.user.role == 'admin' ? 'danger' : 'info'}">${data.user.role.toUpperCase()}</span></td>
                                        </tr>
                                        <tr>
                                            <th>Status:</th>
                                            <td>${data.user.is_suspended ? '<span class="badge bg-danger">Suspended</span>' : '<span class="badge bg-success">Active</span>'}</td>
                                        </tr>
                                        <tr>
                                            <th>Joined:</th>
                                            <td>${data.user.created_at}</td>
                                        </tr>
                                        <tr>
                                            <th>Lost Reports:</th>
                                            <td>${data.stats.lost_reports}</td>
                                        </tr>
                                        <tr>
                                            <th>Found Reports:</th>
                                            <td>${data.stats.found_reports}</td>
                                        </tr>
                                        <tr>
                                            <th>Claims Made:</th>
                                            <td>${data.stats.claims_made}</td>
                                        </tr>
                                        <tr>
                                            <th>Approved Claims:</th>
                                            <td>${data.stats.approved_claims}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        `;
                        modalContent.innerHTML = html;
                    } else {
                        modalContent.innerHTML = '<div class="alert alert-danger">Failed to load user details</div>';
                    }
                })
                .catch(error => {
                    modalContent.innerHTML = '<div class="alert alert-danger">Error loading user details</div>';
                });
        }
        
        function addAdmin() {
            const form = document.getElementById('addAdminForm');
            const formData = new FormData(form);
            formData.append('action', 'add_admin');
            
            fetch('../../Backend/Admin/manage_accounts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Admin account created successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error creating admin: ' + error);
            });
        }
    </script>
</body>
</html>