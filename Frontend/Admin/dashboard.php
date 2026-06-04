<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

// Check if user is admin
checkAdmin();

// Get system statistics
$stats = [];

// Total users
$query = "SELECT COUNT(*) as total FROM users";
$result = mysqli_query($conn, $query);
$stats['total_users'] = mysqli_fetch_assoc($result)['total'];

// Total admins
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'admin'";
$result = mysqli_query($conn, $query);
$stats['total_admins'] = mysqli_fetch_assoc($result)['total'];

// Total regular users
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'user'";
$result = mysqli_query($conn, $query);
$stats['total_regular_users'] = mysqli_fetch_assoc($result)['total'];

// Total lost reports
$query = "SELECT COUNT(*) as total FROM lost_reports";
$result = mysqli_query($conn, $query);
$stats['total_lost'] = mysqli_fetch_assoc($result)['total'];

// Total found reports
$query = "SELECT COUNT(*) as total FROM found_reports";
$result = mysqli_query($conn, $query);
$stats['total_found'] = mysqli_fetch_assoc($result)['total'];

// Pending approvals
$query = "SELECT COUNT(*) as total FROM found_reports WHERE status = 'pending'";
$result = mysqli_query($conn, $query);
$stats['pending_approvals'] = mysqli_fetch_assoc($result)['total'];

// Total claims
$query = "SELECT COUNT(*) as total FROM claims";
$result = mysqli_query($conn, $query);
$stats['total_claims'] = mysqli_fetch_assoc($result)['total'];

// Pending claims
$query = "SELECT COUNT(*) as total FROM claims WHERE status = 'pending'";
$result = mysqli_query($conn, $query);
$stats['pending_claims'] = mysqli_fetch_assoc($result)['total'];

// Approved claims
$query = "SELECT COUNT(*) as total FROM claims WHERE status = 'approved'";
$result = mysqli_query($conn, $query);
$stats['approved_claims'] = mysqli_fetch_assoc($result)['total'];

// Recent users (last 5)
$query = "SELECT * FROM users ORDER BY created_at DESC LIMIT 5";
$recent_users = mysqli_query($conn, $query);

// Recent reports (last 5)
$query = "SELECT 'lost' as type, id, item_name, user_id, created_at FROM lost_reports 
          UNION ALL 
          SELECT 'found' as type, id, item_name, user_id, created_at FROM found_reports 
          ORDER BY created_at DESC LIMIT 5";
$recent_reports = mysqli_query($conn, $query);

// Monthly report statistics for chart
$query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(CASE WHEN report_type = 'lost' THEN 1 END) as lost_count,
            COUNT(CASE WHEN report_type = 'found' THEN 1 END) as found_count
          FROM (
              SELECT 'lost' as report_type, created_at FROM lost_reports
              UNION ALL
              SELECT 'found' as report_type, created_at FROM found_reports
          ) as all_reports
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
          ORDER BY month DESC";
$monthly_stats = mysqli_query($conn, $query);

// Prepare data for chart
$months = [];
$lost_data = [];
$found_data = [];
while($row = mysqli_fetch_assoc($monthly_stats)) {
    $months[] = $row['month'];
    $lost_data[] = $row['lost_count'];
    $found_data[] = $row['found_count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Lost & Found System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .sidebar a:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 25px;
        }
        .sidebar .active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .stat-card {
            transition: transform 0.3s;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .navbar-top {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
                    <a href="dashboard.php" class="active">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="approve.php">
                        <i class="fas fa-check-circle"></i> Approve Items
                        <?php if($stats['pending_approvals'] > 0): ?>
                            <span class="badge bg-danger float-end"><?php echo $stats['pending_approvals']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="recyclebin.php">
                        <i class="fas fa-trash-restore"></i> Recycle Bin
                    </a>
                    <a href="handle_claims.php">
                        <i class="fas fa-handshake"></i> Manage Claims
                        <?php if($stats['pending_claims'] > 0): ?>
                            <span class="badge bg-warning float-end"><?php echo $stats['pending_claims']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="manage_accounts.php">
                        <i class="fas fa-users-cog"></i> Manage Accounts
                    </a>
                    <a href="reports.php">
                        <i class="fas fa-chart-line"></i> Reports
                    </a>
                    <a href="settings.php">
                        <i class="fas fa-cog"></i> Settings
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
                        <h4 class="mb-0">Dashboard</h4>
                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#"><i class="fas fa-user"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </nav>

                <!-- Statistics Cards -->
                <div class="p-4">
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
                                        <i class="fas fa-user-plus"></i> Admins: <?php echo $stats['total_admins']; ?> | 
                                        Users: <?php echo $stats['total_regular_users']; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Lost Reports</h6>
                                            <h2 class="mb-0"><?php echo $stats['total_lost']; ?></h2>
                                        </div>
                                        <i class="fas fa-frown fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Found Reports</h6>
                                            <h2 class="mb-0"><?php echo $stats['total_found']; ?></h2>
                                        </div>
                                        <i class="fas fa-smile fa-3x opacity-50"></i>
                                    </div>
                                    <small class="mt-2 d-block">
                                        <i class="fas fa-clock"></i> Pending: <?php echo $stats['pending_approvals']; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title">Claims</h6>
                                            <h2 class="mb-0"><?php echo $stats['total_claims']; ?></h2>
                                        </div>
                                        <i class="fas fa-handshake fa-3x opacity-50"></i>
                                    </div>
                                    <small class="mt-2 d-block">
                                        <i class="fas fa-check"></i> Approved: <?php echo $stats['approved_claims']; ?> | 
                                        Pending: <?php echo $stats['pending_claims']; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-chart-line"></i> Reports Overview (Last 6 Months)</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="reportChart" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-chart-pie"></i> System Overview</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="overviewChart" height="250"></canvas>
                                    <div class="mt-3 text-center">
                                        <small class="text-muted">
                                            <i class="fas fa-chart-simple"></i> Success Rate: 
                                            <?php 
                                            $success_rate = ($stats['total_claims'] > 0) ? 
                                                round(($stats['approved_claims'] / $stats['total_claims']) * 100, 1) : 0;
                                            echo $success_rate; ?>%
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Users and Reports -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-users"></i> Recent Users</h5>
                                    <a href="manage_accounts.php" class="btn btn-sm btn-primary float-end">View All</a>
                                </div>
                                <div class="card-body">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Joined</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($user = mysqli_fetch_assoc($recent_users)): ?>
                                            <tr>
                                                <td>
                                                    <i class="fas fa-user-circle"></i> 
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['role'] == 'admin' ? 'danger' : 'info'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-clock"></i> Recent Activities</h5>
                                </div>
                                <div class="card-body">
                                    <div class="timeline">
                                        <?php while($report = mysqli_fetch_assoc($recent_reports)): ?>
                                        <div class="mb-3 pb-3 border-bottom">
                                            <div class="d-flex">
                                                <div class="me-3">
                                                    <?php if($report['type'] == 'lost'): ?>
                                                        <i class="fas fa-frown text-danger fa-2x"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-smile text-success fa-2x"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($report['item_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-tag"></i> <?php echo ucfirst($report['type']); ?> report
                                                        <i class="fas fa-calendar ms-2"></i> 
                                                        <?php echo date('M d, Y H:i', strtotime($report['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                                    <div class="row mt-3">
                                        <div class="col-md-3">
                                            <a href="approve.php" class="btn btn-success w-100">
                                                <i class="fas fa-check-circle"></i> Approve Items
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="handle_claims.php" class="btn btn-info w-100">
                                                <i class="fas fa-handshake"></i> Handle Claims
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="manage_accounts.php" class="btn btn-primary w-100">
                                                <i class="fas fa-user-plus"></i> Add Admin
                                            </a>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="reports.php" class="btn btn-secondary w-100">
                                                <i class="fas fa-download"></i> Export Reports
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Report Chart
        const ctx1 = document.getElementById('reportChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_reverse($months)); ?>,
                datasets: [{
                    label: 'Lost Reports',
                    data: <?php echo json_encode(array_reverse($lost_data)); ?>,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Found Reports',
                    data: <?php echo json_encode(array_reverse($found_data)); ?>,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });

        // Overview Chart
        const ctx2 = document.getElementById('overviewChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Lost Reports', 'Found Reports', 'Claims'],
                datasets: [{
                    data: [<?php echo $stats['total_lost']; ?>, <?php echo $stats['total_found']; ?>, <?php echo $stats['total_claims']; ?>],
                    backgroundColor: ['#dc3545', '#28a745', '#ffc107'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    </script>
</body>
</html>