<?php
session_start();
require_once "../config/db_connect.php";

$step = isset($_GET['step']) ? $_GET['step'] : 1;

if($_SERVER["REQUEST_METHOD"] == "POST") {
    if($step == 1) {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        
        $query = "SELECT * FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $query);
        
        if(mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $update = "UPDATE users SET reset_token = '$token', reset_expires = '$expires' WHERE email = '$email'";
            mysqli_query($conn, $update);
            
            // In production, send email here
            $_SESSION['reset_token'] = $token;
            $success = "Password reset link has been sent to your email!";
            $step = 2;
        } else {
            $error = "Email not found!";
        }
    } elseif($step == 2 && isset($_POST['token'])) {
        $token = $_POST['token'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if($new_password !== $confirm_password) {
            $error = "Passwords do not match!";
        } elseif(strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters!";
        } else {
            $check = "SELECT * FROM users WHERE reset_token = '$token' AND reset_expires > NOW()";
            $result = mysqli_query($conn, $check);
            
            if(mysqli_num_rows($result) == 1) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update = "UPDATE users SET password = '$hashed_password', reset_token = NULL, reset_expires = NULL WHERE reset_token = '$token'";
                mysqli_query($conn, $update);
                
                $success = "Password reset successful! You can now login.";
                $step = 3;
            } else {
                $error = "Invalid or expired reset token!";
            }
        }
    }
}

// For demo purposes, show reset form with token from session
if($step == 2 && isset($_SESSION['reset_token'])) {
    $demo_token = $_SESSION['reset_token'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
        }
        .reset-container {
            margin-top: 100px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container reset-container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header text-center bg-white">
                        <i class="fas fa-key fa-3x text-warning mb-3"></i>
                        <h3>Reset Password</h3>
                    </div>
                    <div class="card-body">
                        <?php if(isset($success)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="login.php" class="btn btn-primary">Go to Login</a>
                            </div>
                        <?php elseif(isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($step == 1 && !isset($success)): ?>
                        <form method="POST" action="?step=1">
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       placeholder="Enter your registered email">
                                <small class="text-muted">We'll send a password reset link to this email</small>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane"></i> Send Reset Link
                            </button>
                        </form>
                        <?php elseif($step == 2 && !isset($success) && isset($demo_token)): ?>
                        <form method="POST" action="?step=2">
                            <input type="hidden" name="token" value="<?php echo $demo_token; ?>">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">
                                    <i class="fas fa-lock"></i> New Password
                                </label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required 
                                       placeholder="Enter new password (min. 6 characters)">
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-check-circle"></i> Confirm New Password
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                                       placeholder="Confirm your new password">
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-save"></i> Reset Password
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <a href="login.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left"></i> Back to Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>