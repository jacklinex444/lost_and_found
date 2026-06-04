<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkLogin();

$user_id = $_SESSION['user_id'];

// Handle claim submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_claim'])) {
    $lost_report_id = mysqli_real_escape_string($conn, $_POST['lost_report_id']);
    $found_report_id = mysqli_real_escape_string($conn, $_POST['found_report_id']);
    
    // Verify lost report belongs to user
    $check_lost = "SELECT id FROM lost_reports WHERE id = $lost_report_id AND user_id = $user_id";
    $lost_result = mysqli_query($conn, $check_lost);
    
    if(mysqli_num_rows($lost_result) > 0) {
        $query = "INSERT INTO claims (lost_report_id, found_report_id, claimant_id, status) 
                  VALUES ('$lost_report_id', '$found_report_id', '$user_id', 'pending')";
        
        if(mysqli_query($conn, $query)) {
            $claim_success = "Claim submitted successfully! Admin will review your claim.";
        } else {
            $claim_error = "Error submitting claim: " . mysqli_error($conn);
        }
    } else {
        $claim_error = "Invalid report selection.";
    }
}

// Get all reports
$lost_query = "SELECT *, 'lost' as type FROM lost_reports WHERE user_id = $user_id ORDER BY created_at DESC";
$found_query = "SELECT *, 'found' as type FROM found_reports WHERE user_id = $user_id ORDER BY created_at DESC";

$lost_reports = mysqli_query($conn, $lost_query);
$found_reports = mysqli_query($conn, $found_query);

// Get claims made by user
$claims_query = "SELECT c.*, l.item_name as lost_item, f.item_name as found_item, f.location as found_location
                FROM claims c
                JOIN lost_reports l ON c.lost_report_id = l.id
                JOIN found_reports f ON c.found_report_id = f.id
                WHERE c.claimant_id = $user_id
                ORDER BY c.created_at DESC";
$claims = mysqli_query($conn, $claims_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports - Lost & Found System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f0f2f5;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .report-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            border-left: 4px solid;
            cursor: pointer;
        }
        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .report-card.lost {
            border-left-color: #dc3545;
        }
        .report-card.found {
            border-left-color: #28a745;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }
        .claim-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #ffc107;
        }
        .nav-tabs .nav-link {
            color: #333;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .btn-claim {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            border: none;
            color: white;
        }
        .btn-claim:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">
                <i class="fas fa-search"></i> Lost & Found System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="lost_reports.php">
                            <i class="fas fa-frown"></i> Report Lost
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="found_items.php">
                            <i class="fas fa-smile"></i> Report Found
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="myReports.php">
                            <i class="fas fa-list"></i> My Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../../auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Alert Messages -->
        <?php if(isset($claim_success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $claim_success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($claim_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $claim_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#lost" type="button" role="tab">
                    <i class="fas fa-frown"></i> Lost Items 
                    <span class="badge bg-danger"><?php echo mysqli_num_rows($lost_reports); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#found" type="button" role="tab">
                    <i class="fas fa-smile"></i> Found Items
                    <span class="badge bg-success"><?php echo mysqli_num_rows($found_reports); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#claims" type="button" role="tab">
                    <i class="fas fa-handshake"></i> My Claims
                    <span class="badge bg-warning"><?php echo mysqli_num_rows($claims); ?></span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Lost Reports Tab -->
            <div class="tab-pane fade show active" id="lost" role="tabpanel">
                <?php if(mysqli_num_rows($lost_reports) > 0): ?>
                    <?php while($report = mysqli_fetch_assoc($lost_reports)): ?>
                    <div class="report-card lost" onclick="viewReportDetails(<?php echo $report['id']; ?>, 'lost')">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="mb-2"><?php echo htmlspecialchars($report['item_name']); ?></h5>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars(substr($report['description'], 0, 100)); ?></p>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt"></i> Lost at: <?php echo htmlspecialchars($report['location']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> Date: <?php echo date('M d, Y', strtotime($report['date_lost'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($report['category']); ?></span>
                                <br>
                                <small class="text-muted">Reported: <?php echo date('M d, Y', strtotime($report['created_at'])); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 bg-white rounded">
                        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                        <h5>No Lost Reports</h5>
                        <p class="text-muted">You haven't reported any lost items yet.</p>
                        <a href="lost_reports.php" class="btn btn-danger">Report Lost Item</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Found Reports Tab -->
            <div class="tab-pane fade" id="found" role="tabpanel">
                <?php if(mysqli_num_rows($found_reports) > 0): ?>
                    <?php while($report = mysqli_fetch_assoc($found_reports)): ?>
                    <div class="report-card found" onclick="viewReportDetails(<?php echo $report['id']; ?>, 'found')">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="mb-2"><?php echo htmlspecialchars($report['item_name']); ?></h5>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars(substr($report['description'], 0, 100)); ?></p>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt"></i> Found at: <?php echo htmlspecialchars($report['location']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> Date: <?php echo date('M d, Y', strtotime($report['date_found'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($report['category']); ?></span>
                                <br>
                                <span class="status-badge bg-<?php 
                                    echo $report['status'] == 'approved' ? 'success' : 
                                        ($report['status'] == 'pending' ? 'warning' : 'danger'); 
                                ?> text-white">
                                    <?php echo ucfirst($report['status']); ?>
                                </span>
                                <br>
                                <small class="text-muted">Reported: <?php echo date('M d, Y', strtotime($report['created_at'])); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 bg-white rounded">
                        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                        <h5>No Found Reports</h5>
                        <p class="text-muted">You haven't reported any found items yet.</p>
                        <a href="found_items.php" class="btn btn-success">Report Found Item</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Claims Tab -->
            <div class="tab-pane fade" id="claims" role="tabpanel">
                <?php if(mysqli_num_rows($claims) > 0): ?>
                    <?php while($claim = mysqli_fetch_assoc($claims)): ?>
                    <div class="claim-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-2">
                                    Claim for: <strong><?php echo htmlspecialchars($claim['lost_item']); ?></strong>
                                </h6>
                                <p class="small mb-1">
                                    <i class="fas fa-box"></i> Found item: <?php echo htmlspecialchars($claim['found_item']); ?>
                                </p>
                                <p class="small mb-1">
                                    <i class="fas fa-map-marker-alt"></i> Found at: <?php echo htmlspecialchars($claim['found_location']); ?>
                                </p>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> Claimed on: <?php echo date('M d, Y H:i', strtotime($claim['created_at'])); ?>
                                </small>
                            </div>
                            <div>
                                <span class="badge bg-<?php 
                                    echo $claim['status'] == 'approved' ? 'success' : 
                                        ($claim['status'] == 'pending' ? 'warning' : 'danger'); 
                                ?>">
                                    <?php echo strtoupper($claim['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 bg-white rounded">
                        <i class="fas fa-handshake fa-4x text-muted mb-3"></i>
                        <h5>No Claims Made</h5>
                        <p class="text-muted">You haven't submitted any claims yet.</p>
                        <p class="text-muted small">When you find a matching item, you can claim it from the matches section.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Report Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportModalTitle">Report Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="reportModalBody">
                    <!-- Dynamic content -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewReportDetails(id, type) {
            const modalBody = document.getElementById('reportModalBody');
            modalBody.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"></div><p>Loading...</p></div>';
            
            // Fetch report details via AJAX
            fetch(`../../Backend/Users/get_report_details.php?id=${id}&type=${type}`)
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        let html = `
                            <div class="row">
                                <div class="col-md-12">
                                    <h5>${data.report.item_name}</h5>
                                    <p class="text-muted">${data.report.description || 'No description provided'}</p>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong><i class="fas fa-tag"></i> Category:</strong>
                                            <p>${data.report.category}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <strong><i class="fas fa-map-marker-alt"></i> Location:</strong>
                                            <p>${data.report.location}</p>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong><i class="fas fa-calendar"></i> ${type == 'lost' ? 'Date Lost:' : 'Date Found:'}</strong>
                                            <p>${data.report.date_lost || data.report.date_found}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <strong><i class="fas fa-clock"></i> Reported:</strong>
                                            <p>${new Date(data.report.created_at).toLocaleString()}</p>
                                        </div>
                                    </div>
                        `;
                        
                        if(type == 'found' && data.report.status == 'approved') {
                            html += `
                                <div class="alert alert-success mt-3">
                                    <i class="fas fa-check-circle"></i> This item has been approved and is available for claiming.
                                </div>
                            `;
                        }
                        
                        if(data.matches && data.matches.length > 0) {
                            html += `<hr><h6><i class="fas fa-handshake"></i> Potential Matches (${data.matches.length})</h6>`;
                            data.matches.forEach(match => {
                                html += `
                                    <div class="alert alert-info">
                                        <strong>${match.item_name}</strong><br>
                                        <small>${match.type == 'found' ? 'Found at: ' + match.location : 'Lost at: ' + match.location}</small>
                                        ${match.type == 'found' && match.status == 'approved' ? 
                                            `<br><button onclick="submitClaim(${match.id}, '${match.type}')" class="btn btn-sm btn-success mt-2">Claim This Item</button>` : ''}
                                    </div>
                                `;
                            });
                        }
                        
                        html += `</div></div>`;
                        modalBody.innerHTML = html;
                    } else {
                        modalBody.innerHTML = '<div class="alert alert-danger">Failed to load report details</div>';
                    }
                })
                .catch(error => {
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading report details</div>';
                });
            
            new bootstrap.Modal(document.getElementById('reportModal')).show();
        }
        
        function submitClaim(foundId, type) {
            if(confirm('Are you sure you want to claim this item? Your request will be reviewed by an admin.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const lostInput = document.createElement('input');
                lostInput.type = 'hidden';
                lostInput.name = 'lost_report_id';
                lostInput.value = <?php echo isset($report) ? $report['id'] : 0; ?>;
                
                const foundInput = document.createElement('input');
                foundInput.type = 'hidden';
                foundInput.name = 'found_report_id';
                foundInput.value = foundId;
                
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'submit_claim';
                submitInput.value = '1';
                
                form.appendChild(lostInput);
                form.appendChild(foundInput);
                form.appendChild(submitInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>