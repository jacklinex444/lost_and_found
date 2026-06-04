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
    $check_lost = "SELECT id, item_name FROM lost_reports WHERE id = $lost_report_id AND user_id = $user_id";
    $lost_result = mysqli_query($conn, $check_lost);
    
    if(mysqli_num_rows($lost_result) > 0) {
        $lost = mysqli_fetch_assoc($lost_result);
        
        // Check if claim already exists
        $check_claim = "SELECT id FROM claims WHERE lost_report_id = $lost_report_id AND found_report_id = $found_report_id";
        $claim_result = mysqli_query($conn, $check_claim);
        
        if(mysqli_num_rows($claim_result) == 0) {
            $query = "INSERT INTO claims (lost_report_id, found_report_id, claimant_id, status, created_at) 
                      VALUES ('$lost_report_id', '$found_report_id', '$user_id', 'pending', NOW())";
            
            if(mysqli_query($conn, $query)) {
                // Get finder info to notify them
                $get_finder = "SELECT user_id FROM found_reports WHERE id = $found_report_id";
                $finder_result = mysqli_query($conn, $get_finder);
                $finder = mysqli_fetch_assoc($finder_result);
                
                // Notify the finder
                $notify_query = "INSERT INTO notifications (user_id, title, message, created_at) 
                                VALUES ('{$finder['user_id']}', 'New Claim Submitted', 
                                       'Someone has claimed your found item \"{$lost['item_name']}\". Please wait for admin review.', NOW())";
                mysqli_query($conn, $notify_query);
                
                $claim_success = "Claim submitted successfully! Admin will review your claim.";
            } else {
                $claim_error = "Error submitting claim: " . mysqli_error($conn);
            }
        } else {
            $claim_error = "You have already claimed this item!";
        }
    } else {
        $claim_error = "Invalid lost report.";
    }
}

// Get user's lost reports that have potential matches
$lost_reports_query = "SELECT l.*, 
                        (SELECT COUNT(*) FROM found_reports f 
                         WHERE f.category = l.category 
                         AND f.status = 'approved'
                         AND (f.item_name LIKE CONCAT('%', l.item_name, '%') 
                              OR l.item_name LIKE CONCAT('%', f.item_name, '%'))
                        ) as match_count
                       FROM lost_reports l
                       WHERE l.user_id = $user_id
                       HAVING match_count > 0
                       ORDER BY l.created_at DESC";
$lost_reports = mysqli_query($conn, $lost_reports_query);

// Get user's pending claims
$pending_claims_query = "SELECT c.*, l.item_name as lost_item, f.item_name as found_item, 
                                f.location as found_location, f.photo
                         FROM claims c
                         JOIN lost_reports l ON c.lost_report_id = l.id
                         JOIN found_reports f ON c.found_report_id = f.id
                         WHERE c.claimant_id = $user_id AND c.status = 'pending'
                         ORDER BY c.created_at DESC";
$pending_claims = mysqli_query($conn, $pending_claims_query);

// Get user's approved claims
$approved_claims_query = "SELECT c.*, l.item_name as lost_item, f.item_name as found_item, 
                                 f.location as found_location
                          FROM claims c
                          JOIN lost_reports l ON c.lost_report_id = l.id
                          JOIN found_reports f ON c.found_report_id = f.id
                          WHERE c.claimant_id = $user_id AND c.status = 'approved'
                          ORDER BY c.updated_at DESC";
$approved_claims = mysqli_query($conn, $approved_claims_query);

// Get user's rejected claims
$rejected_claims_query = "SELECT c.*, l.item_name as lost_item, f.item_name as found_item, 
                                 f.location as found_location, c.admin_notes
                          FROM claims c
                          JOIN lost_reports l ON c.lost_report_id = l.id
                          JOIN found_reports f ON c.found_report_id = f.id
                          WHERE c.claimant_id = $user_id AND c.status = 'rejected'
                          ORDER BY c.updated_at DESC";
$rejected_claims = mysqli_query($conn, $rejected_claims_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Items - Lost & Found System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f0f2f5;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .claim-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            border-left: 4px solid;
        }
        .claim-card.available {
            border-left-color: #28a745;
        }
        .claim-card.pending {
            border-left-color: #ffc107;
        }
        .claim-card.approved {
            border-left-color: #17a2b8;
        }
        .claim-card.rejected {
            border-left-color: #dc3545;
        }
        .claim-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .match-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            transition: all 0.3s;
        }
        .match-item:hover {
            background: #e9ecef;
        }
        .btn-claim {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
        }
        .btn-claim:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .match-photo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
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
                        <a class="nav-link" href="myReports.php">
                            <i class="fas fa-list"></i> My Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="claim_items.php">
                            <i class="fas fa-handshake"></i> Claim Items
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../../auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h4 class="mb-2">
                            <i class="fas fa-handshake"></i> Claim Your Lost Items
                        </h4>
                        <p class="mb-0">Found a match? Submit a claim to get your item back!</p>
                    </div>
                </div>
            </div>
        </div>

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
        <ul class="nav nav-tabs mb-4" id="claimTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#available" type="button" role="tab">
                    <i class="fas fa-gift"></i> Available to Claim 
                    <span class="badge bg-success"><?php echo mysqli_num_rows($lost_reports); ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                    <i class="fas fa-clock"></i> Pending Claims
                    <span class="badge bg-warning"><?php echo mysqli_num_rows($pending_claims); ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab">
                    <i class="fas fa-check-circle"></i> Approved Claims
                    <span class="badge bg-info"><?php echo mysqli_num_rows($approved_claims); ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#rejected" type="button" role="tab">
                    <i class="fas fa-times-circle"></i> Rejected Claims
                    <span class="badge bg-danger"><?php echo mysqli_num_rows($rejected_claims); ?></span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Available to Claim Tab -->
            <div class="tab-pane fade show active" id="available" role="tabpanel">
                <?php if(mysqli_num_rows($lost_reports) > 0): ?>
                    <?php while($lost = mysqli_fetch_assoc($lost_reports)): ?>
                        <div class="claim-card available">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1">
                                        <i class="fas fa-frown text-danger"></i> 
                                        <?php echo htmlspecialchars($lost['item_name']); ?>
                                    </h5>
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt"></i> Lost at: <?php echo htmlspecialchars($lost['location']); ?> |
                                        <i class="fas fa-calendar"></i> Lost on: <?php echo date('M d, Y', strtotime($lost['date_lost'])); ?>
                                    </small>
                                </div>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($lost['category']); ?></span>
                            </div>
                            
                            <p class="text-muted small mb-3"><?php echo nl2br(htmlspecialchars($lost['description'])); ?></p>
                            
                            <!-- Find Matching Items -->
                            <?php
                            $match_query = "SELECT f.*, u.username, u.email 
                                           FROM found_reports f
                                           JOIN users u ON f.user_id = u.id
                                           WHERE f.category = '{$lost['category']}' 
                                           AND f.status = 'approved'
                                           AND (f.item_name LIKE '%{$lost['item_name']}%' 
                                                OR '{$lost['item_name']}' LIKE CONCAT('%', f.item_name, '%'))
                                           ORDER BY f.created_at DESC";
                            $matches = mysqli_query($conn, $match_query);
                            ?>
                            
                            <?php if(mysqli_num_rows($matches) > 0): ?>
                                <h6 class="mt-3 mb-2">
                                    <i class="fas fa-handshake"></i> Potential Matches Found:
                                </h6>
                                <?php while($match = mysqli_fetch_assoc($matches)): ?>
                                    <div class="match-item">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <?php if(isset($match['photo']) && $match['photo'] && file_exists("../../" . $match['photo'])): ?>
                                                    <img src="../../<?php echo $match['photo']; ?>" class="match-photo" alt="Found item">
                                                <?php else: ?>
                                                    <div class="match-photo bg-secondary text-white d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-image fa-2x"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong><?php echo htmlspecialchars($match['item_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt"></i> Found at: <?php echo htmlspecialchars($match['location']); ?>
                                                </small>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-user"></i> Reported by: <?php echo htmlspecialchars($match['username']); ?>
                                                </small>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <form method="POST" action="" onsubmit="return confirm('Are you sure you want to claim this item? Your request will be reviewed by an admin.');">
                                                    <input type="hidden" name="lost_report_id" value="<?php echo $lost['id']; ?>">
                                                    <input type="hidden" name="found_report_id" value="<?php echo $match['id']; ?>">
                                                    <button type="submit" name="submit_claim" class="btn btn-claim">
                                                        <i class="fas fa-hand-holding-heart"></i> Claim This Item
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle"></i> No matching found items available yet. Check back later!
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 bg-white rounded">
                        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                        <h5>No Lost Items to Claim</h5>
                        <p class="text-muted">You haven't reported any lost items that have potential matches.</p>
                        <a href="lost_reports.php" class="btn btn-danger">Report Lost Item</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pending Claims Tab -->
            <div class="tab-pane fade" id="pending" role="tabpanel">
                <?php if(mysqli_num_rows($pending_claims) > 0): ?>
                    <?php while($claim = mysqli_fetch_assoc($pending_claims)): ?>
                        <div class="claim-card pending">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-2">
                                        <i class="fas fa-box"></i> Claim for: <strong><?php echo htmlspecialchars($claim['lost_item']); ?></strong>
                                    </h6>
                                    <p class="small mb-1">
                                        <i class="fas fa-search"></i> Found item: <?php echo htmlspecialchars($claim['found_item']); ?>
                                    </p>
                                    <p class="small mb-1">
                                        <i class="fas fa-map-marker-alt"></i> Found at: <?php echo htmlspecialchars($claim['found_location']); ?>
                                    </p>
                                    <?php if(isset($claim['photo']) && $claim['photo']): ?>
                                        <img src="../../<?php echo $claim['photo']; ?>" style="width: 80px; border-radius: 5px;" class="mt-2">
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <span class="status-badge bg-warning text-dark">
                                        <i class="fas fa-clock"></i> Pending Review
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        Claimed: <?php echo date('M d, Y H:i', strtotime($claim['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 bg-white rounded">
                        <i class="fas fa-clock fa-4x text-muted mb-3"></i>
                        <h5>No Pending Claims</h5>
                        <p class="text-muted">You don't have any pending claims at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Approved Claims Tab -->
            <div class="tab-pane fade" id="approved" role="tabpanel">
                <?php if(mysqli_num_rows($approved_claims) > 0): ?>
                    <?php while($claim = mysqli_fetch_assoc($approved_claims)): ?>
                        <div class="claim-card approved">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-2">
                                        <i class="fas fa-check-circle text-success"></i> 
                                        <strong><?php echo htmlspecialchars($claim['lost_item']); ?></strong>
                                    </h6>
                                    <p class="small mb-1">
                                        <i class="fas fa-smile"></i> Found item: <?php echo htmlspecialchars($claim['found_item']); ?>
                                    </p>
                                    <p class="small mb-1">
                                        <i class="fas fa-map-marker-alt"></i> Location: <?php echo htmlspecialchars($claim['found_location']); ?>
                                    </p>
                                    <div class="alert alert-success mt-2">
                                        <i class="fas fa-gift"></i> <strong>Congratulations!</strong> Your claim has been approved. 
                                        Please contact the admin to arrange collection of your item.
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="status-badge bg-success">
                                        <i class="fas fa-check"></i> Approved
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        Approved: <?php echo date('M d, Y H:i', strtotime($claim['updated_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 bg-white rounded">
                        <i class="fas fa-check-circle fa-4x text-muted mb-3"></i>
                        <h5>No Approved Claims</h5>
                        <p class="text-muted">You don't have any approved claims yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Rejected Claims Tab -->
            <div class="tab-pane fade" id="rejected" role="tabpanel">
                <?php if(mysqli_num_rows($rejected_claims) > 0): ?>
                    <?php while($claim = mysqli_fetch_assoc($rejected_claims)): ?>
                        <div class="claim-card rejected">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-2">
                                        <i class="fas fa-times-circle text-danger"></i> 
                                        <strong><?php echo htmlspecialchars($claim['lost_item']); ?></strong>
                                    </h6>
                                    <p class="small mb-1">
                                        <i class="fas fa-box"></i> Found item: <?php echo htmlspecialchars($claim['found_item']); ?>
                                    </p>
                                    <?php if($claim['admin_notes']): ?>
                                        <div class="alert alert-warning mt-2">
                                            <i class="fas fa-comment"></i> <strong>Reason:</strong> <?php echo htmlspecialchars($claim['admin_notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <span class="status-badge bg-danger">
                                        <i class="fas fa-times"></i> Rejected
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y H:i', strtotime($claim['updated_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 bg-white rounded">
                        <i class="fas fa-times-circle fa-4x text-muted mb-3"></i>
                        <h5>No Rejected Claims</h5>
                        <p class="text-muted">You don't have any rejected claims.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- How to Claim Guide -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6><i class="fas fa-question-circle"></i> How to Claim Your Lost Item:</h6>
                        <ol class="small mb-0">
                            <li>Report your lost item in <strong>"Report Lost"</strong> page</li>
                            <li>Wait for the system to find matching found items</li>
                            <li>Go to <strong>"Claim Items"</strong> page to see available matches</li>
                            <li>Click <strong>"Claim This Item"</strong> on the matching item</li>
                            <li>Wait for admin to review and approve your claim</li>
                            <li>Once approved, contact admin to collect your item</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>