<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkAdmin();

// Handle bulk actions
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $items = isset($_POST['items']) ? $_POST['items'] : [];
    
    if(!empty($items)) {
        $ids = implode(',', array_map('intval', $items));
        if($action == 'approve') {
            $query = "UPDATE found_reports SET status = 'approved' WHERE id IN ($ids)";
            mysqli_query($conn, $query);
            $success = count($items) . " items approved successfully!";
        } elseif($action == 'reject') {
            $query = "UPDATE found_reports SET status = 'rejected' WHERE id IN ($ids)";
            mysqli_query($conn, $query);
            $success = count($items) . " items rejected successfully!";
        }
    }
}

// Get filter parameters
$category = isset($_GET['category']) ? $_GET['category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$where = "WHERE f.status = '$status'";
if($category) {
    $where .= " AND f.category = '$category'";
}
if($search) {
    $where .= " AND (f.item_name LIKE '%$search%' OR f.description LIKE '%$search%' OR f.location LIKE '%$search%')";
}

// Get pending found reports with user details
$query = "SELECT f.*, u.username, u.email, u.id as user_id 
          FROM found_reports f 
          JOIN users u ON f.user_id = u.id 
          $where
          ORDER BY f.created_at DESC";
$pending_items = mysqli_query($conn, $query);

// Get categories for filter
$cat_query = "SELECT DISTINCT category FROM found_reports";
$categories = mysqli_query($conn, $cat_query);

// Get counts for different statuses
$count_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM found_reports WHERE status='pending'"))['count'];
$count_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM found_reports WHERE status='approved'"))['count'];
$count_rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM found_reports WHERE status='rejected'"))['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Items - Admin Dashboard</title>
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
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }
        .item-card {
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
                    <a href="approve.php" class="active"><i class="fas fa-check-circle"></i> Approve Items</a>
                    <a href="recyclebin.php"><i class="fas fa-trash-restore"></i> Recycle Bin</a>
                    <a href="handle_claims.php"><i class="fas fa-handshake"></i> Manage Claims</a>
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
                        <h4 class="mb-0">Approve Found Items</h4>
                        <div>
                            <span class="text-muted me-3">
                                <i class="fas fa-user"></i> <?php echo $_SESSION['username']; ?>
                            </span>
                        </div>
                    </div>
                </nav>

                <div class="p-4">
                    <!-- Status Tabs -->
                    <ul class="nav nav-tabs mb-4">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status == 'pending' ? 'active' : ''; ?>" href="?status=pending">
                                Pending <span class="badge bg-warning ms-1"><?php echo $count_pending; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status == 'approved' ? 'active' : ''; ?>" href="?status=approved">
                                Approved <span class="badge bg-success ms-1"><?php echo $count_approved; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status == 'rejected' ? 'active' : ''; ?>" href="?status=rejected">
                                Rejected <span class="badge bg-danger ms-1"><?php echo $count_rejected; ?></span>
                            </a>
                        </li>
                    </ul>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <input type="hidden" name="status" value="<?php echo $status; ?>">
                                <div class="col-md-4">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control" placeholder="Search by item name, description, location..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-control">
                                        <option value="">All Categories</option>
                                        <?php while($cat = mysqli_fetch_assoc($categories)): ?>
                                        <option value="<?php echo $cat['category']; ?>" <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                            <?php echo $cat['category']; ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary d-block">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <a href="approve.php?status=<?php echo $status; ?>" class="btn btn-secondary d-block">
                                        <i class="fas fa-sync"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <?php if($status == 'pending' && mysqli_num_rows($pending_items) > 0): ?>
                    <div class="card mb-4">
                        <div class="card-body">
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

                    <!-- Items Grid -->
                    <div class="row">
                        <?php if(mysqli_num_rows($pending_items) > 0): ?>
                            <?php while($item = mysqli_fetch_assoc($pending_items)): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card item-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title mb-0">
                                                <?php echo htmlspecialchars($item['item_name']); ?>
                                            </h5>
                                            <?php if($status == 'pending'): ?>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input item-checkbox" 
                                                       name="items[]" value="<?php echo $item['id']; ?>" form="bulkForm">
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <span class="badge bg-secondary"><?php echo $item['category']; ?></span>
                                            <span class="status-badge bg-<?php 
                                                echo $item['status'] == 'approved' ? 'success' : 
                                                    ($item['status'] == 'pending' ? 'warning' : 'danger'); 
                                            ?> text-white">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>
                                        </div>
                                        
                                        <p class="card-text text-muted small">
                                            <?php echo nl2br(htmlspecialchars(substr($item['description'], 0, 100))); ?>
                                            <?php echo strlen($item['description']) > 100 ? '...' : ''; ?>
                                        </p>
                                        
                                        <div class="mt-3">
                                            <div class="mb-1">
                                                <i class="fas fa-map-marker-alt text-danger"></i> 
                                                <?php echo htmlspecialchars($item['location']); ?>
                                            </div>
                                            <div class="mb-1">
                                                <i class="fas fa-calendar text-info"></i> 
                                                Found: <?php echo date('M d, Y', strtotime($item['date_found'])); ?>
                                            </div>
                                            <div class="mb-2">
                                                <i class="fas fa-user text-success"></i> 
                                                Reported by: <?php echo htmlspecialchars($item['username']); ?>
                                            </div>
                                            <div class="text-muted small">
                                                <i class="fas fa-clock"></i> 
                                                <?php echo date('M d, Y H:i', strtotime($item['created_at'])); ?>
                                            </div>
                                        </div>
                                        
                                        <?php if($status == 'pending'): ?>
                                        <div class="mt-3">
                                            <hr>
                                            <div class="btn-group w-100" role="group">
                                                <button onclick="approveItem(<?php echo $item['id']; ?>)" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button onclick="viewDetails(<?php echo $item['id']; ?>)" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button onclick="rejectItem(<?php echo $item['id']; ?>)" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                        </div>
                                        <?php else: ?>
                                        <div class="mt-3">
                                            <hr>
                                            <button onclick="viewDetails(<?php echo $item['id']; ?>)" class="btn btn-info btn-sm w-100">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info text-center">
                                    <i class="fas fa-info-circle"></i> No <?php echo $status; ?> items found.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Item Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalContent">
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
        function approveItem(id) {
            if(confirm('Are you sure you want to approve this item?')) {
                window.location.href = "../../Backend/Admin/support_approve.php?action=approve&id=" + id;
            }
        }
        
        function rejectItem(id) {
            if(confirm('Are you sure you want to reject this item?')) {
                window.location.href = "../../Backend/Admin/support_approve.php?action=reject&id=" + id;
            }
        }
        
        function viewDetails(id) {
            // In a real implementation, you would fetch details via AJAX
            // For now, redirect to a details page
            window.open('item_details.php?id=' + id, '_blank', 'width=800,height=600');
        }
        
        function selectAll() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            checkboxes.forEach(cb => cb.checked = !allChecked);
        }
    </script>
</body>
</html>