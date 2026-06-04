<?php
// Main landing page for Lost and Found System
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost and Found System - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
            margin-bottom: 50px;
        }
        .feature-card {
            transition: transform 0.3s;
            margin-bottom: 30px;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .stats {
            background: #f8f9fa;
            padding: 50px 0;
        }
        .stat-number {
            font-size: 48px;
            font-weight: bold;
            color: #667eea;
        }
        footer {
            background: #2d3748;
            color: white;
            padding: 30px 0;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-search"></i> Lost & Found System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="Frontend/Public_Users/lost_reports.php">
                                <i class="fas fa-frown"></i> Report Lost
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="Frontend/Public_Users/found_items.php">
                                <i class="fas fa-smile"></i> Report Found
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="Frontend/Public_Users/myReports.php">
                                <i class="fas fa-list"></i> My Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout (<?php echo $_SESSION['username']; ?>)
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/login.php">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/signup.php">
                                <i class="fas fa-user-plus"></i> Sign Up
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero">
        <div class="container text-center">
            <h1 class="display-4">Welcome to Lost & Found System</h1>
            <p class="lead">Reconnect people with their lost belongings efficiently and securely</p>
            <?php if(!isset($_SESSION['user_id'])): ?>
                <a href="auth/signup.php" class="btn btn-light btn-lg">Get Started</a>
                <a href="auth/login.php" class="btn btn-outline-light btn-lg">Login</a>
            <?php else: ?>
                <a href="Frontend/Public_Users/lost_reports.php" class="btn btn-light btn-lg">Report Lost Item</a>
                <a href="Frontend/Public_Users/found_items.php" class="btn btn-outline-light btn-lg">Report Found Item</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Features Section -->
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <div class="card feature-card">
                    <div class="card-body text-center">
                        <i class="fas fa-frown fa-3x text-primary mb-3"></i>
                        <h4>Report Lost Items</h4>
                        <p>Easily report items you've lost with detailed descriptions, categories, and locations.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card feature-card">
                    <div class="card-body text-center">
                        <i class="fas fa-smile fa-3x text-success mb-3"></i>
                        <h4>Report Found Items</h4>
                        <p>Help others by reporting items you've found. Submit with photos and location details.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card feature-card">
                    <div class="card-body text-center">
                        <i class="fas fa-handshake fa-3x text-warning mb-3"></i>
                        <h4>Claim Items</h4>
                        <p>Find your lost items and submit claims. Our system helps verify ownership.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- How It Works -->
    <div class="stats">
        <div class="container">
            <h2 class="text-center mb-5">How It Works</h2>
            <div class="row">
                <div class="col-md-3 text-center">
                    <div class="stat-number">1</div>
                    <h5>Report</h5>
                    <p>Report lost or found items</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="stat-number">2</div>
                    <h5>Match</h5>
                    <p>System matches lost and found items</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="stat-number">3</div>
                    <h5>Claim</h5>
                    <p>Submit claim for matching items</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="stat-number">4</div>
                    <h5>Reunite</h5>
                    <p>Get your items back!</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container text-center">
            <p>&copy; 2024 Lost and Found System. All rights reserved.</p>
            <p>Helping people reconnect with their lost belongings</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>