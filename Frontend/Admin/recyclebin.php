<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkAdmin();

// Get deleted items with filters
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$where = "1=1";
if($type != 'all') {
    $where .= " AND report_type = '$type'";
}
if($search) {
    $where .= " AND (item_name LIKE '%$search%' OR description LIKE '%$search%')";
}

$query = "SELECT * FROM deleted_items WHERE $where ORDER BY deleted_at DESC";
$deleted_items = mysqli_query($conn, $query);

// Get counts
$count_lost = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM deleted_items WHERE report_type='lost'"))['count'];
$count_found = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM deleted_items WHERE report_type='found'"))['count'];
$count_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM deleted_items"))['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin - Admin Dashboard</title>
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
        .deleted-item {
            background: white;
            border-left: 4px solid #dc3545;
        }
        .restore-item {
            border-left-color: #28a745;
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
                    <a href="recyclebin.php" class="active"><i class="fas fa-trash-restore"></i> Recycle Bin</a>
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
                        <h4 class="mb-0">Recycle Bin</h4>
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
                        <div class="col-md-4">
                            <div class="card bg-danger text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6>Lost Items</h6>
                                            <h2 class="mb-0"><?php echo $count_lost; ?></h2>
                                        </div>
                                        <i class="fas fa-frown fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6>Found Items</h6>
                                            <h2 class="mb-0"><?php echo $count_found; ?></h2>
                                        </div>
                                        <i class="fas fa-smile fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-secondary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6>Total Deleted</h6>
                                            <h2 class="mb-0"><?php echo $count_total; ?></h2>
                                        </div>
                                        <i class="fas fa-trash fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Report Type</label>
                                    <select name="type" class="form-control" onchange="this.form.submit()">
                                        <option value="all" <?php echo $type == 'all' ? 'selected' : ''; ?>>All Types</option>
                                        <option value="lost" <?php echo $type == 'lost' ? 'selected' : ''; ?>>Lost Reports</option>
                                        <option value="found" <?php echo $type == 'found' ? 'selected' : ''; ?>>Found Reports</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Search</label>
                                    <input type="text" name="search" class="form-control" placeholder="Search by item name..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary d-block w-100">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Deleted Items List -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-trash-restore"></i> Deleted Items</h5>
                            <button onclick="emptyRecycleBin()" class="btn btn-danger btn-sm float-end">
                                <i class="fas fa-trash-alt"></i> Empty Recycle Bin
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if(mysqli_num_rows($deleted_items) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Item Name</th>
                                                <th>Type</th>
                                                <th>Category</th>
                                                <th>Location</th>
                                                <th>Deleted By</th>
                                                <th>Deleted At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($item = mysqli_fetch_assoc($deleted_items)): ?>
                                            <tr class="deleted-item">
                                                <td><?php echo $item['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">Original ID: <?php echo $item['original_id']; ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $item['report_type'] == 'lost' ? 'danger' : 'success'; ?>">
                                                        <?php echo ucfirst($item['report_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                <td><?php echo htmlspecialchars($item['location']); ?></td>
                                                <td>
                                                    <i class="fas fa-user"></i> 
                                                    <?php echo ucfirst($item['deleted_by']); ?>
                                                </td>
                                                <td>
                                                    <i class="fas fa-clock"></i> 
                                                    <?php echo date('M d, Y H:i', strtotime($item['deleted_at'])); ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button onclick="viewItem(<?php echo $item['id']; ?>)" class="btn btn-info" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button onclick="restoreItem(<?php echo $item['id']; ?>)" class="btn btn-success" title="Restore">
                                                            <i class="fas fa-trash-restore"></i>
                                                        </button>
                                                        <button onclick="permanentDelete(<?php echo $item['id']; ?>)" class="btn btn-danger" title="Permanently Delete">
                                                            <i class="fas fa-times-circle"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    <i class="fas fa-info-circle fa-3x mb-3"></i>
                                    <h5>Recycle Bin is Empty</h5>
                                    <p>No deleted items found.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Information Note -->
                    <div class="alert alert-warning mt-4">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Note:</strong> Items in recycle bin are stored for 30 days before automatic permanent deletion. 
                        Restored items will return to their original tables.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Item Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Deleted Item Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewModalContent">
                    <!-- Content loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function restoreItem(id) {
            if(confirm('Are you sure you want to restore this item? It will be moved back to its original table.')) {
                window.location.href = "../../Backend/Admin/recycle.php?action=restore&id=" + id;
            }
        }
        
        function permanentDelete(id) {
            if(confirm('WARNING: This action cannot be undone! Are you sure you want to permanently delete this item?')) {
                window.location.href = "../../Backend/Admin/recycle.php?action=permanent&id=" + id;
            }
        }
        
        function emptyRecycleBin() {
            if(confirm('WARNING: This will permanently delete ALL items in the recycle bin. This action cannot be undone! Continue?')) {
                window.location.href = "../../Backend/Admin/recycle.php?action=empty";
            }
        }
        
        function viewItem(id) {
            // Fetch item details via AJAX and display in modal
            fetch('../../Backend/Admin/get_item_details.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div class="mb-3">
                            <label class="fw-bold">Item Name:</label>
                            <p>${data.item_name}</p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Category:</label>
                            <p>${data.category}</p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Description:</label>
                            <p>${data.description || 'No description provided'}</p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Location:</label>
                            <p>${data.location}</p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Date Reported:</label>
                            <p>${data.date_reported}</p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Deleted By:</label>
                            <p>${data.deleted_by}</p>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Deleted At:</label>
                            <p>${data.deleted_at}</p>
                        </div>
                    `;
                    document.getElementById('viewModalContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('viewModal')).show();
                });
        }
    </script>
</body>
</html>