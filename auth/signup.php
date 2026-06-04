<?php
session_start();
require_once "../config/db_connect.php";

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    $errors = [];
    
    if($password !== $confirm_password) {
        $errors[] = "Passwords do not match!";
    }
    
    if(strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long!";
    }
    
    // Check if email exists
    $check_email = "SELECT * FROM users WHERE email = '$email'";
    $email_result = mysqli_query($conn, $check_email);
    
    if(mysqli_num_rows($email_result) > 0) {
        $errors[] = "Email already registered!";
    }
    
    // Check if username exists
    $check_username = "SELECT * FROM users WHERE username = '$username'";
    $username_result = mysqli_query($conn, $check_username);
    
    if(mysqli_num_rows($username_result) > 0) {
        $errors[] = "Username already taken!";
    }
    
    if(empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$hashed_password', 'user')";
        
        if(mysqli_query($conn, $query)) {
            $success = "Registration successful! You can now login.";
        } else {
            $errors[] = "Registration failed: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .signup-container {
            margin-top: 50px;
            margin-bottom: 50px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .card-header {
            background: white;
            border-bottom: none;
            text-align: center;
            padding-top: 30px;
        }
        .btn-signup {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container signup-container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-plus fa-3x text-success mb-3"></i>
                        <h3>Create an Account</h3>
                        <p class="text-muted">Join our Lost & Found community</p>
                    </div>
                    <div class="card-body">
                        <?php if(isset($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <div class="text-center mt-3">
                                <a href="login.php" class="btn btn-primary">Proceed to Login</a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($errors)): ?>
                            <?php foreach($errors as $error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if(!isset($success)): ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user"></i> Username
                                </label>
                                <input type="text" class="form-control" id="username" name="username" required 
                                       placeholder="Choose a username" value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock"></i> Password
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required 
                                       placeholder="Create a password (min. 6 characters)">
                                <small class="text-muted">Password must be at least 6 characters long</small>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-check-circle"></i> Confirm Password
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                                       placeholder="Confirm your password">
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                                </label>
                            </div>
                            <button type="submit" class="btn btn-signup w-100">
                                <i class="fas fa-user-plus"></i> Sign Up
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-0">
                                Already have an account? 
                                <a href="login.php" class="text-decoration-none">
                                    <i class="fas fa-sign-in-alt"></i> Login here
                                </a>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Acceptance of Terms</h6>
                    <p>By registering for the Lost and Found System, you agree to these terms.</p>
                    <h6>2. User Responsibilities</h6>
                    <p>Users must provide accurate information when reporting lost or found items.</p>
                    <h6>3. Item Claims</h6>
                    <p>Claims must be legitimate and verifiable. False claims may result in account suspension.</p>
                    <h6>4. Privacy</h6>
                    <p>Your personal information will be protected and not shared without consent.</p>
                    <h6>5. Code of Conduct</h6>
                    <p>Users must treat others with respect and not misuse the system.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>