<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkLogin();

// Handle AJAX requests
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    try {
        $user_id = $_SESSION['user_id'];
        $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $location = mysqli_real_escape_string($conn, $_POST['location']);
        $date_found = mysqli_real_escape_string($conn, $_POST['date_found']);
        
        // Handle photo upload
        $photo_path = null;
        $upload_error = null;
        
        if(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['photo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $file_size = $_FILES['photo']['size'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            // Validate file
            if(!in_array($ext, $allowed)) {
                $upload_error = "Only JPG, PNG, GIF, and WEBP files are allowed.";
            } elseif($file_size > $max_size) {
                $upload_error = "File size must be less than 5MB.";
            } else {
                // Create directory if it doesn't exist
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . "/lost_and_found/uploads/found_items/";
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $new_filename = uniqid() . '_' . time() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                // Move uploaded file
                if(move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                    $photo_path = "uploads/found_items/" . $new_filename;
                } else {
                    $upload_error = "Failed to upload image. Check folder permissions.";
                }
            }
        }
        
        // Insert into database
        $photo_field = $photo_path ? "'$photo_path'" : "NULL";
        $query = "INSERT INTO found_reports (user_id, item_name, category, description, location, date_found, photo, status, created_at) 
                  VALUES ('$user_id', '$item_name', '$category', '$description', '$location', '$date_found', $photo_field, 'pending', NOW())";
        
        if(mysqli_query($conn, $query)) {
            $report_id = mysqli_insert_id($conn);
            
            // Check for matching lost items
            $match_query = "SELECT l.*, u.username, u.email 
                           FROM lost_reports l
                           JOIN users u ON l.user_id = u.id
                           WHERE l.category = '$category' 
                           AND (l.item_name LIKE '%$item_name%' OR '$item_name' LIKE CONCAT('%', l.item_name, '%'))
                           LIMIT 5";
            $matches = mysqli_query($conn, $match_query);
            $matching_items = [];
            while($match = mysqli_fetch_assoc($matches)) {
                $matching_items[] = $match;
            }
            
            $response = [
                'success' => true, 
                'message' => 'Found report submitted successfully! Awaiting admin approval.', 
                'report_id' => $report_id, 
                'matches' => $matching_items
            ];
            
            if($photo_path) {
                $response['photo'] = $photo_path;
                $response['photo_url'] = '/lost_and_found/' . $photo_path;
            }
            
            if($upload_error) {
                $response['photo_warning'] = $upload_error;
            }
            
            echo json_encode($response);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database Error: ' . mysqli_error($conn)]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
    }
    exit();
}

// Get user's recent found reports
$user_id = $_SESSION['user_id'];
$recent_query = "SELECT * FROM found_reports WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 5";
$recent_reports = mysqli_query($conn, $recent_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Found Item - Lost & Found System</title>
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
            border-left: 4px solid #28a745;
        }
        .recent-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .btn-submit {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            border: none;
            padding: 12px 30px;
            font-weight: bold;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }
        .preview-image {
            max-width: 150px;
            max-height: 150px;
            margin-top: 10px;
            border-radius: 5px;
            border: 2px solid #28a745;
            padding: 3px;
        }
        .recent-photo {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
            margin-right: 10px;
        }
        .photo-container {
            position: relative;
            display: inline-block;
        }
        .remove-photo {
            position: absolute;
            top: -5px;
            right: -5px;
            background: red;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            text-align: center;
            font-size: 12px;
            cursor: pointer;
            line-height: 20px;
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
                        <a class="nav-link active" href="found_items.php">
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
                        <i class="fas fa-smile text-success"></i> Report Found Item
                    </h3>
                    <p class="text-muted">Found something? Help reunite it with its owner by reporting it here.</p>
                    
                    <form id="foundReportForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-tag"></i> Item Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="item_name" required 
                                   placeholder="e.g., iPhone 13, Wallet, Keys">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-th-large"></i> Category <span class="text-danger">*</span>
                            </label>
                            <select class="form-control" name="category" required>
                                <option value="">Select Category</option>
                                <option value="Electronics">📱 Electronics</option>
                                <option value="Documents">📄 Documents</option>
                                <option value="Accessories">⌚ Accessories</option>
                                <option value="Clothing">👕 Clothing</option>
                                <option value="Keys">🔑 Keys</option>
                                <option value="Books">📚 Books</option>
                                <option value="Wallet/Purse">👛 Wallet/Purse</option>
                                <option value="Sports Equipment">⚽ Sports Equipment</option>
                                <option value="Medical">🏥 Medical</option>
                                <option value="Others">📦 Others</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-align-left"></i> Description
                            </label>
                            <textarea class="form-control" name="description" rows="4" 
                                      placeholder="Describe the item (color, brand, condition, etc.)"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-map-marker-alt"></i> Location Found <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="location" required 
                                   placeholder="e.g., Main Hall, Parking Lot, Cafeteria">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-calendar"></i> Date Found <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" name="date_found" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-camera"></i> Photo (Optional)
                            </label>
                            <input type="file" class="form-control" name="photo" accept="image/*" id="photoInput">
                            <small class="text-muted">Upload a photo of the found item (Max: 5MB, Formats: JPG, PNG, GIF)</small>
                            <div id="photoPreview" class="mt-2"></div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> All found item reports require admin approval before being visible to others.
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-submit w-100">
                            <i class="fas fa-paper-plane"></i> Submit Found Report
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="col-md-5">
                <!-- Recent Reports -->
                <div class="form-container">
                    <h5 class="mb-3">
                        <i class="fas fa-history"></i> Your Recent Found Reports
                    </h5>
                    <?php if($recent_reports && mysqli_num_rows($recent_reports) > 0): ?>
                        <?php while($report = mysqli_fetch_assoc($recent_reports)): ?>
                        <div class="recent-card">
                            <div class="d-flex">
                                <?php if(isset($report['photo']) && $report['photo'] && file_exists("../../" . $report['photo'])): ?>
                                    <img src="../../<?php echo $report['photo']; ?>" class="recent-photo" alt="Item photo">
                                <?php else: ?>
                                    <div class="recent-photo bg-secondary text-white d-flex align-items-center justify-content-center">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($report['item_name']); ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($report['location']); ?>
                                    </small><br>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> Found on: <?php echo date('M d, Y', strtotime($report['date_found'])); ?>
                                    </small>
                                </div>
                                <div>
                                    <span class="badge bg-<?php 
                                        $status = isset($report['status']) ? $report['status'] : 'pending';
                                        echo $status == 'approved' ? 'success' : ($status == 'pending' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <div class="text-center mt-3">
                            <a href="myReports.php" class="btn btn-sm btn-outline-primary">View All Reports</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No found reports yet. Submit your first report above!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Guidelines Card -->
                <div class="card bg-warning mt-3">
                    <div class="card-body">
                        <h6><i class="fas fa-gavel"></i> Guidelines for Found Items</h6>
                        <ul class="small mb-0">
                            <li>Be honest and accurate in your description</li>
                            <li>Do not claim items that don't belong to you</li>
                            <li>Submit clear photos if possible</li>
                            <li>Wait for admin approval before claiming</li>
                            <li>Help reunite items with their rightful owners</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
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
        let currentPhotoFile = null;
        
        // Photo preview with remove option
        document.getElementById('photoInput').addEventListener('change', function(e) {
            const preview = document.getElementById('photoPreview');
            preview.innerHTML = '';
            
            if(this.files && this.files[0]) {
                currentPhotoFile = this.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const container = document.createElement('div');
                    container.className = 'photo-container';
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'preview-image';
                    
                    const removeBtn = document.createElement('div');
                    removeBtn.className = 'remove-photo';
                    removeBtn.innerHTML = '×';
                    removeBtn.onclick = function() {
                        preview.innerHTML = '';
                        document.getElementById('photoInput').value = '';
                        currentPhotoFile = null;
                    };
                    
                    container.appendChild(img);
                    container.appendChild(removeBtn);
                    preview.appendChild(container);
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        // Form submission
        document.getElementById('foundReportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate file size
            if(currentPhotoFile && currentPhotoFile.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB!');
                return;
            }
            
            const formData = new FormData(this);
            const submitBtn = document.querySelector('.btn-submit');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            submitBtn.disabled = true;
            
            fetch('found_items.php', {
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
                    
                    if(data.photo_url) {
                        html += `
                            <div class="text-center mt-3">
                                <img src="${data.photo_url}" class="img-fluid rounded" style="max-height: 200px;" alt="Uploaded photo">
                                <p class="text-muted small mt-2">Photo uploaded successfully!</p>
                            </div>
                        `;
                    }
                    
                    if(data.photo_warning) {
                        html += `<div class="alert alert-warning mt-2">⚠️ ${data.photo_warning}</div>`;
                    }
                    
                    html += `<p class="mt-3">Your report will be reviewed by an admin. You will be notified once approved.</p>`;
                    
                    if(data.matches && data.matches.length > 0) {
                        html += `<hr><h6><i class="fas fa-handshake"></i> Potential Matches Found!</h6>`;
                        data.matches.forEach(match => {
                            html += `
                                <div class="alert alert-info">
                                    <strong>Someone lost a ${escapeHtml(match.item_name)}</strong><br>
                                    <small>Reported by: ${escapeHtml(match.username)}</small>
                                </div>
                            `;
                        });
                    }
                    
                    document.getElementById('successModalBody').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('successModal')).show();
                    document.getElementById('foundReportForm').reset();
                    document.getElementById('photoPreview').innerHTML = '';
                    currentPhotoFile = null;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.\nError: ' + error.message);
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Set max date to today
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.querySelector('input[name="date_found"]');
        if(dateInput) {
            dateInput.setAttribute('max', today);
        }
        
        // Check PHP configuration for file uploads
        console.log('Upload max size: <?php echo ini_get("upload_max_filesize"); ?>');
    </script>
</body>
</html>