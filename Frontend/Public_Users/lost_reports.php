<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkLogin();

// Handle AJAX requests
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    $user_id = $_SESSION['user_id'];
    $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $date_lost = mysqli_real_escape_string($conn, $_POST['date_lost']);
    
    $query = "INSERT INTO lost_reports (user_id, item_name, category, description, location, date_lost) 
              VALUES ('$user_id', '$item_name', '$category', '$description', '$location', '$date_lost')";
    
    if(mysqli_query($conn, $query)) {
        $report_id = mysqli_insert_id($conn);
        
        // Check for matching found items
        $match_query = "SELECT f.*, u.username, u.email 
                       FROM found_reports f
                       JOIN users u ON f.user_id = u.id
                       WHERE f.category = '$category' 
                       AND f.status = 'approved'
                       AND (f.item_name LIKE '%$item_name%' OR '$item_name' LIKE CONCAT('%', f.item_name, '%'))
                       LIMIT 5";
        $matches = mysqli_query($conn, $match_query);
        $matching_items = [];
        while($match = mysqli_fetch_assoc($matches)) {
            $matching_items[] = $match;
        }
        
        echo json_encode(['success' => true, 'message' => 'Lost report submitted successfully!', 'report_id' => $report_id, 'matches' => $matching_items]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . mysqli_error($conn)]);
    }
    exit();
}

// Get user's recent lost reports
$user_id = $_SESSION['user_id'];
$recent_query = "SELECT * FROM lost_reports WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 5";
$recent_reports = mysqli_query($conn, $recent_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Lost Item - Lost & Found System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f0f2f5;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        .recent-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: transform 0.3s;
            border-left: 4px solid #dc3545;
        }
        .recent-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .match-card {
            background: #d4edda;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #28a745;
            cursor: pointer;
            transition: all 0.3s;
        }
        .match-card:hover {
            background: #c3e6cb;
            transform: scale(1.02);
        }
        .form-label {
            font-weight: 600;
            color: #333;
        }
        .btn-submit {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            padding: 12px 30px;
            font-weight: bold;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,53,69,0.3);
        }
        .category-badge {
            position: absolute;
            top: 10px;
            right: 10px;
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
                        <a class="nav-link active" href="lost_reports.php">
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
                        <a class="nav-link" href="../../auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-7">
                <!-- Report Form -->
                <div class="form-container">
                    <h3 class="mb-4">
                        <i class="fas fa-frown text-danger"></i> Report Lost Item
                    </h3>
                    <p class="text-muted">Fill out the form below to report an item you've lost.</p>
                    
                    <form id="lostReportForm">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-tag"></i> Item Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="item_name" required 
                                   placeholder="e.g., iPhone 13, Wallet, Laptop">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-th-large"></i> Category <span class="text-danger">*</span>
                            </label>
                            <select class="form-control" name="category" required>
                                <option value="">Select Category</option>
                                <option value="Electronics">📱 Electronics (Phones, Laptops, Tablets)</option>
                                <option value="Documents">📄 Documents (IDs, Passports, Certificates)</option>
                                <option value="Accessories">⌚ Accessories (Watches, Jewelry, Bags)</option>
                                <option value="Clothing">👕 Clothing (Jackets, Shoes, Hats)</option>
                                <option value="Keys">🔑 Keys (House keys, Car keys)</option>
                                <option value="Books">📚 Books (Textbooks, Notebooks)</option>
                                <option value="Wallet/Purse">👛 Wallet/Purse</option>
                                <option value="Sports Equipment">⚽ Sports Equipment</option>
                                <option value="Medical">🏥 Medical (Glasses, Hearing aids)</option>
                                <option value="Others">📦 Others</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-align-left"></i> Description
                            </label>
                            <textarea class="form-control" name="description" rows="4" 
                                      placeholder="Describe the item in detail (color, brand, unique markings, etc.)"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-map-marker-alt"></i> Location Lost <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="location" required 
                                   placeholder="e.g., Central Library, Bus Station, Coffee Shop">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-calendar"></i> Date Lost <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" name="date_lost" required>
                        </div>
                        
                        <button type="submit" class="btn btn-danger btn-submit w-100">
                            <i class="fas fa-paper-plane"></i> Submit Lost Report
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="col-md-5">
                <!-- Recent Reports -->
                <div class="form-container">
                    <h5 class="mb-3">
                        <i class="fas fa-history"></i> Your Recent Lost Reports
                    </h5>
                    <?php if(mysqli_num_rows($recent_reports) > 0): ?>
                        <?php while($report = mysqli_fetch_assoc($recent_reports)): ?>
                        <div class="recent-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($report['item_name']); ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($report['location']); ?>
                                    </small><br>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> Lost on: <?php echo date('M d, Y', strtotime($report['date_lost'])); ?>
                                    </small>
                                </div>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($report['category']); ?></span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <div class="text-center mt-3">
                            <a href="myReports.php" class="btn btn-sm btn-outline-primary">View All Reports</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No lost reports yet. Submit your first report above!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tips Card -->
                <div class="card bg-info text-white mt-3">
                    <div class="card-body">
                        <h6><i class="fas fa-lightbulb"></i> Tips for Reporting Lost Items</h6>
                        <ul class="small mb-0">
                            <li>Be as specific as possible in your description</li>
                            <li>Mention any unique identifiers or markings</li>
                            <li>Include the exact location where it was lost</li>
                            <li>Upload a photo if available (coming soon)</li>
                            <li>Check back frequently for potential matches</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle"></i> Report Submitted!
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="successModalBody">
                    <!-- Dynamic content -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="myReports.php" class="btn btn-primary">View My Reports</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('lostReportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('lost_reports.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    let html = `
                        <p><i class="fas fa-check-circle text-success"></i> ${data.message}</p>
                        <p><strong>Report ID:</strong> #${data.report_id}</p>
                    `;
                    
                    if(data.matches && data.matches.length > 0) {
                        html += `<hr><h6><i class="fas fa-handshake"></i> Potential Matches Found!</h6>`;
                        data.matches.forEach(match => {
                            html += `
                                <div class="alert alert-success">
                                    <strong>${match.item_name}</strong> was found at ${match.location}<br>
                                    <small>Reported by: ${match.username}</small>
                                </div>
                            `;
                        });
                        html += `<p class="text-muted small">You can check these matches in your dashboard.</p>`;
                    }
                    
                    document.getElementById('successModalBody').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('successModal')).show();
                    document.getElementById('lostReportForm').reset();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
        
        // Set max date to today for date input
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="date_lost"]').setAttribute('max', today);
    </script>
</body>
</html>