<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkAdmin();

// Handle bulk actions
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $claims = isset($_POST['claims']) ? $_POST['claims'] : [];
    
    if(!empty($claims)) {
        $ids = implode(',', array_map('intval', $claims));
        if($action == 'approve') {
            $query = "UPDATE claims SET status = 'approved', updated_at = NOW() WHERE id IN ($ids)";
            mysqli_query($conn, $query);
            $success = count($claims) . " claims approved successfully!";
            
            // Add notification for each claim
            $notify_query = "INSERT INTO notifications (user_id, title, message) 
                            SELECT claimant_id, 'Claim Approved', 'Your claim has been approved! Please contact admin to collect your item.'
                            FROM claims WHERE id IN ($ids)";
            mysqli_query($conn, $notify_query);
        } elseif($action == 'reject') {
            $query = "UPDATE claims SET status = 'rejected', updated_at = NOW() WHERE id IN ($ids)";
            mysqli_query($conn, $query);
            $success = count($claims) . " claims rejected successfully!";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$where = "1=1";
if($status_filter != 'all') {
    $where .= " AND c.status = '$status_filter'";
}
if($search) {
    $where .= " AND (l.item_name LIKE '%$search%' OR f.item_name LIKE '%$search%' OR u.username LIKE '%$search%')";
}

// Get all claims with details
$query = "SELECT c.*, 
          l.item_name as lost_item, l.description as lost_description, l.location as lost_location, l.date_lost,
          f.item_name as found_item, f.description as found_description, f.location as found_location, f.date_found,
          u.username as claimant_name, u.email as claimant_email, u.id as claimant_id
          FROM claims c
          JOIN lost_reports l ON c.lost_report_id = l.id
          JOIN found_reports f ON c.found_report_id = f.id
          JOIN users u ON c.claimant_id = u.id
          WHERE $where
          ORDER BY 
            CASE WHEN c.status = 'pending' THEN 1 ELSE 2 END,
            c.created_at DESC";
$claims = mysqli_query($conn, $query);

// Get counts for statistics
$count_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM claims WHERE status='pending'"))['count'];
$count_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM claims WHERE status='approved'"))['count'];
$count_rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM claims WHERE status='rejected'"))['count'];
$count_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM claims"))['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Claims - Admin Dashboard</title>
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
        .claim-card {
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        .claim-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .match-score {
            font-size: 24px;
            font-weight: bold;
        }
        .detail-label {
            font-weight: bold;
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        .detail-value {
            margin-bottom: 10px;
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
                </div>
                <hr class="bg-light">
                <nav class="nav flex-column">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="approve.php"><i class="fas fa-check-circle"></i> Approve Items</a>
                    <a href="recyclebin.php"><i class="fas fa-trash-restore"></i> Recycle Bin</a>
                    <a href="handle_claims.php" class="active"><i class="fas fa-handshake"></i> Manage Claims</a>
                    <a href="manage_accounts.php"><i class="fas fa-users-cog"></i> Manage Accounts</a>
                    <hr class="bg-light">
                    <a href="../../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-0 main-content">
                <!-- Top Navbar -->
                <nav class="navbar-top px-4 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Manage Claims</h4>
                        <div>
                            <span class="text-muted">
                                <i class="fas fa-user"></i> <?php echo $_SESSION['username']; ?>
                            </span>
                        </div>
                    </div>
                </nav>

                <div class="p-4">
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6>Total Claims</h6>
                                            <h2 class="mb-0"><?php echo $count_total; ?></h2>
                                        </div>
                                        <i class="fas fa-chart-line fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6>Pending Claims</h6>
                                            <h2 class="mb-0"><?php echo $count_pending; ?></h2>
                                        </div>
                                        <i class="fas fa-clock fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6>Approved Claims</h6>
                                            <h2 class="mb-0"><?php echo $count_approved; ?></h2>
                                        </div>
                                        <i class="fas fa-check-circle fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6>Rejected Claims</h6>
                                            <h2 class="mb-0"><?php echo $count_rejected; ?></h2>
                                        </div>
                                        <i class="fas fa-times-circle fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Tabs -->
                    <ul class="nav nav-tabs mb-4">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter == 'all' ? 'active' : ''; ?>" href="?status=all">
                                All <span class="badge bg-secondary ms-1"><?php echo $count_total; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter == 'pending' ? 'active' : ''; ?>" href="?status=pending">
                                Pending <span class="badge bg-warning ms-1"><?php echo $count_pending; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter == 'approved' ? 'active' : ''; ?>" href="?status=approved">
                                Approved <span class="badge bg-success ms-1"><?php echo $count_approved; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status_filter == 'rejected' ? 'active' : ''; ?>" href="?status=rejected">
                                Rejected <span class="badge bg-danger ms-1"><?php echo $count_rejected; ?></span>
                            </a>
                        </li>
                    </ul>

                    <!-- Filters and Bulk Actions -->
                    <?php if(mysqli_num_rows($claims) > 0): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <input type="text" name="search" class="form-control" placeholder="Search by item name or claimant..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                                <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            </form>
                            
                            <?php if($status_filter == 'pending'): ?>
                            <form method="POST" id="bulkForm">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <select name="bulk_action" class="form-control" required>
                                            <option value="">Bulk Actions</option>
                                            <option value="approve">Approve Selected</option>
                                            <option value="reject">Reject Selected</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-check-double"></i> Apply
                                        </button>
                                    </div>
                                    <div class="col-md-7 text-end">
                                        <button type="button" class="btn btn-link" onclick="selectAll()">
                                            <i class="fas fa-check-square"></i> Select All
                                        </button>
                                    </div>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Success/Error Messages -->
                    <?php if(isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Claims List -->
                    <?php if(mysqli_num_rows($claims) > 0): ?>
                        <?php while($claim = mysqli_fetch_assoc($claims)): ?>
                        <div class="card claim-card">
                            <div class="card-body">
                                <div class="row">
                                    <!-- Lost Item Section -->
                                    <div class="col-md-5">
                                        <div class="border-end pe-3">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="text-danger mb-0">
                                                    <i class="fas fa-frown"></i> Lost Item
                                                </h5>
                                                <?php if($status_filter == 'pending'): ?>
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input claim-checkbox" 
                                                           name="claims[]" value="<?php echo $claim['id']; ?>" form="bulkForm">
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="detail-label">Item Name</div>
                                            <div class="detail-value">
                                                <strong><?php echo htmlspecialchars($claim['lost_item']); ?></strong>
                                            </div>
                                            <div class="detail-label">Description</div>
                                            <div class="detail-value"><?php echo nl2br(htmlspecialchars(substr($claim['lost_description'], 0, 100))); ?></div>
                                            <div class="detail-label">Location Lost</div>
                                            <div class="detail-value">
                                                <i class="fas fa-map-marker-alt text-danger"></i> 
                                                <?php echo htmlspecialchars($claim['lost_location']); ?>
                                            </div>
                                            <div class="detail-label">Date Lost</div>
                                            <div class="detail-value">
                                                <i class="fas fa-calendar text-info"></i> 
                                                <?php echo date('M d, Y', strtotime($claim['date_lost'])); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Found Item Section -->
                                    <div class="col-md-5">
                                        <div class="pe-3">
                                            <h5 class="text-success mb-3">
                                                <i class="fas fa-smile"></i> Found Item
                                            </h5>
                                            <div class="detail-label">Item Name</div>
                                            <div class="detail-value">
                                                <strong><?php echo htmlspecialchars($claim['found_item']); ?></strong>
                                            </div>
                                            <div class="detail-label">Description</div>
                                            <div class="detail-value"><?php echo nl2br(htmlspecialchars(substr($claim['found_description'], 0, 100))); ?></div>
                                            <div class="detail-label">Location Found</div>
                                            <div class="detail-value">
                                                <i class="fas fa-map-marker-alt text-success"></i> 
                                                <?php echo htmlspecialchars($claim['found_location']); ?>
                                            </div>
                                            <div class="detail-label">Date Found</div>
                                            <div class="detail-value">
                                                <i class="fas fa-calendar text-info"></i> 
                                                <?php echo date('M d, Y', strtotime($claim['date_found'])); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Claim Information -->
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <div class="mb-3">
                                                <div class="detail-label">Claimant</div>
                                                <div class="detail-value">
                                                    <i class="fas fa-user-circle fa-2x"></i><br>
                                                    <strong><?php echo htmlspecialchars($claim['claimant_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($claim['claimant_email']); ?></small>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="detail-label">Status</div>
                                                <div class="detail-value">
                                                    <span class="badge bg-<?php 
                                                        echo $claim['status'] == 'approved' ? 'success' : 
                                                            ($claim['status'] == 'pending' ? 'warning' : 'danger'); 
                                                    ?> p-2">
                                                        <?php echo strtoupper($claim['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="detail-label">Claim Date</div>
                                                <div class="detail-value small">
                                                    <?php echo date('M d, Y', strtotime($claim['created_at'])); ?>
                                                </div>
                                            </div>
                                            <?php if($claim['status'] == 'pending'): ?>
                                            <div class="mt-3">
                                                <button onclick="approveClaim(<?php echo $claim['id']; ?>)" class="btn btn-success btn-sm w-100 mb-2">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button onclick="rejectClaim(<?php echo $claim['id']; ?>)" class="btn btn-danger btn-sm w-100">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                                <button onclick="viewFullDetails(<?php echo $claim['id']; ?>)" class="btn btn-info btn-sm w-100 mt-2">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <button onclick="viewFullDetails(<?php echo $claim['id']; ?>)" class="btn btn-info btn-sm w-100">
                                                <i class="fas fa-eye"></i> View Full Details
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle fa-3x mb-3"></i>
                            <h5>No Claims Found</h5>
                            <p>There are no <?php echo $status_filter != 'all' ? $status_filter : ''; ?> claims to display.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Claim Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsModalContent">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function approveClaim(id) {
            if(confirm('Approve this claim? The claimant will be notified.')) {
                window.location.href = "../../Backend/Admin/handle_claims.php?action=update&id=" + id + "&status=approved";
            }
        }
        
        function rejectClaim(id) {
            if(confirm('Reject this claim? This action cannot be undone.')) {
                window.location.href = "../../Backend/Admin/handle_claims.php?action=update&id=" + id + "&status=rejected";
            }
        }
        
        function viewFullDetails(id) {
            // In production, fetch details via AJAX
            window.open('claim_details.php?id=' + id, '_blank', 'width=900,height=700');
        }
        
        function selectAll() {
            const checkboxes = document.querySelectorAll('.claim-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        }
    </script>
</body>
</html>