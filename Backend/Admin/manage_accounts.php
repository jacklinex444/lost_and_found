<?php
session_start();
require_once "../../config/auth_check.php";
require_once "../../config/db_connect.php";

checkAdmin();

function logActivity($conn, $user_id, $action, $details) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $query = "INSERT INTO activity_logs (user_id, action, details, ip_address) 
              VALUES ('$user_id', '$action', '$details', '$ip')";
    mysqli_query($conn, $query);
}

function sendNotification($conn, $user_id, $title, $message) {
    $query = "INSERT INTO notifications (user_id, title, message) 
              VALUES ('$user_id', '$title', '$message')";
    return mysqli_query($conn, $query);
}

// Handle different actions
if(isset($_GET['action'])) {
    $action = $_GET['action'];
    $admin_id = $_SESSION['user_id'];
    
    // Delete user
    if($action == 'delete' && isset($_GET['id'])) {
        $id = mysqli_real_escape_string($conn, $_GET['id']);
        
        // Don't allow deleting own account
        if($id != $_SESSION['user_id']) {
            // Get user details before deletion
            $get_user = "SELECT username, email FROM users WHERE id = $id";
            $user_result = mysqli_query($conn, $get_user);
            $user = mysqli_fetch_assoc($user_result);
            
            if($user) {
                // Start transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // First move user's reports to deleted_items
                    $move_lost = "INSERT INTO deleted_items (original_id, report_type, user_id, item_name, category, description, location, date_reported, deleted_by) 
                                 SELECT id, 'lost', user_id, item_name, category, description, location, date_lost, 'admin'
                                 FROM lost_reports WHERE user_id = $id";
                    mysqli_query($conn, $move_lost);
                    
                    $move_found = "INSERT INTO deleted_items (original_id, report_type, user_id, item_name, category, description, location, date_reported, deleted_by) 
                                 SELECT id, 'found', user_id, item_name, category, description, location, date_found, 'admin'
                                 FROM found_reports WHERE user_id = $id";
                    mysqli_query($conn, $move_found);
                    
                    // Delete user's reports
                    mysqli_query($conn, "DELETE FROM lost_reports WHERE user_id = $id");
                    mysqli_query($conn, "DELETE FROM found_reports WHERE user_id = $id");
                    
                    // Delete user's claims
                    mysqli_query($conn, "DELETE FROM claims WHERE claimant_id = $id");
                    
                    // Delete user
                    $delete_user = "DELETE FROM users WHERE id = $id";
                    mysqli_query($conn, $delete_user);
                    
                    // Log activity
                    logActivity($conn, $admin_id, 'DELETE_USER', 
                               "Deleted user: {$user['username']} ({$user['email']})");
                    
                    mysqli_commit($conn);
                    header("Location: ../../Frontend/Admin/manage_accounts.php?msg=user_deleted");
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    header("Location: ../../Frontend/Admin/manage_accounts.php?error=" . urlencode($e->getMessage()));
                }
            } else {
                header("Location: ../../Frontend/Admin/manage_accounts.php?error=User not found");
            }
        } else {
            header("Location: ../../Frontend/Admin/manage_accounts.php?error=Cannot delete your own account");
        }
    }
    
    // Make user admin
    elseif($action == 'make_admin' && isset($_GET['id'])) {
        $id = mysqli_real_escape_string($conn, $_GET['id']);
        
        $query = "UPDATE users SET role = 'admin' WHERE id = $id";
        if(mysqli_query($conn, $query)) {
            $get_user = "SELECT username FROM users WHERE id = $id";
            $user_result = mysqli_query($conn, $get_user);
            $user = mysqli_fetch_assoc($user_result);
            
            logActivity($conn, $admin_id, 'MAKE_ADMIN', 
                       "Promoted user {$user['username']} to admin");
            
            sendNotification($conn, $id, 'Admin Privileges Granted', 
                           "You have been granted admin privileges. You can now access the admin dashboard.");
            
            header("Location: ../../Frontend/Admin/manage_accounts.php?msg=admin_promoted");
        } else {
            header("Location: ../../Frontend/Admin/manage_accounts.php?error=" . urlencode(mysqli_error($conn)));
        }
    }
    
    // Remove admin privileges
    elseif($action == 'remove_admin' && isset($_GET['id'])) {
        $id = mysqli_real_escape_string($conn, $_GET['id']);
        
        // Don't allow removing your own admin privileges
        if($id != $_SESSION['user_id']) {
            $query = "UPDATE users SET role = 'user' WHERE id = $id";
            if(mysqli_query($conn, $query)) {
                $get_user = "SELECT username FROM users WHERE id = $id";
                $user_result = mysqli_query($conn, $get_user);
                $user = mysqli_fetch_assoc($user_result);
                
                logActivity($conn, $admin_id, 'REMOVE_ADMIN', 
                           "Removed admin privileges from user {$user['username']}");
                
                sendNotification($conn, $id, 'Admin Privileges Removed', 
                               "Your admin privileges have been revoked. You now have regular user access.");
                
                header("Location: ../../Frontend/Admin/manage_accounts.php?msg=admin_removed");
            } else {
                header("Location: ../../Frontend/Admin/manage_accounts.php?error=" . urlencode(mysqli_error($conn)));
            }
        } else {
            header("Location: ../../Frontend/Admin/manage_accounts.php?error=Cannot remove your own admin privileges");
        }
    }
    
    // Suspend user
    elseif($action == 'suspend' && isset($_GET['id'])) {
        $id = mysqli_real_escape_string($conn, $_GET['id']);
        
        // Add suspended field to users table if not exists
        $alter_query = "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_suspended BOOLEAN DEFAULT FALSE";
        mysqli_query($conn, $alter_query);
        
        $query = "UPDATE users SET is_suspended = TRUE WHERE id = $id";
        if(mysqli_query($conn, $query)) {
            $get_user = "SELECT username FROM users WHERE id = $id";
            $user_result = mysqli_query($conn, $get_user);
            $user = mysqli_fetch_assoc($user_result);
            
            logActivity($conn, $admin_id, 'SUSPEND_USER', 
                       "Suspended user: {$user['username']}");
            
            sendNotification($conn, $id, 'Account Suspended', 
                           "Your account has been suspended. Please contact admin for more information.");
            
            header("Location: ../../Frontend/Admin/manage_accounts.php?msg=user_suspended");
        } else {
            header("Location: ../../Frontend/Admin/manage_accounts.php?error=" . urlencode(mysqli_error($conn)));
        }
    }
    
    // Activate user
    elseif($action == 'activate' && isset($_GET['id'])) {
        $id = mysqli_real_escape_string($conn, $_GET['id']);
        
        $query = "UPDATE users SET is_suspended = FALSE WHERE id = $id";
        if(mysqli_query($conn, $query)) {
            $get_user = "SELECT username FROM users WHERE id = $id";
            $user_result = mysqli_query($conn, $get_user);
            $user = mysqli_fetch_assoc($user_result);
            
            logActivity($conn, $admin_id, 'ACTIVATE_USER', 
                       "Activated user: {$user['username']}");
            
            sendNotification($conn, $id, 'Account Activated', 
                           "Your account has been reactivated. You can now login again.");
            
            header("Location: ../../Frontend/Admin/manage_accounts.php?msg=user_activated");
        } else {
            header("Location: ../../Frontend/Admin/manage_accounts.php?error=" . urlencode(mysqli_error($conn)));
        }
    }
    
    // Reset user password
    elseif($action == 'reset_password' && isset($_GET['id'])) {
        $id = mysqli_real_escape_string($conn, $_GET['id']);
        $new_password = password_hash('password123', PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET password = '$new_password' WHERE id = $id";
        if(mysqli_query($conn, $query)) {
            $get_user = "SELECT username, email FROM users WHERE id = $id";
            $user_result = mysqli_query($conn, $get_user);
            $user = mysqli_fetch_assoc($user_result);
            
            logActivity($conn, $admin_id, 'RESET_USER_PASSWORD', 
                       "Reset password for user: {$user['username']}");
            
            sendNotification($conn, $id, 'Password Reset', 
                           "Your password has been reset by admin. New password: password123");
            
            header("Location: ../../Frontend/Admin/manage_accounts.php?msg=password_reset");
        } else {
            header("Location: ../../Frontend/Admin/manage_accounts.php?error=" . urlencode(mysqli_error($conn)));
        }
    }
    
    else {
        header("Location: ../../Frontend/Admin/manage_accounts.php");
    }
}
elseif($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle AJAX requests
    $response = ['success' => false, 'message' => 'Invalid request'];
    
    if(isset($_POST['action']) && $_POST['action'] == 'get_user_stats') {
        $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
        
        $stats = [];
        
        // Get user's report counts
        $lost_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM lost_reports WHERE user_id = $user_id"))['count'];
        $found_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM found_reports WHERE user_id = $user_id"))['count'];
        $claims_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM claims WHERE claimant_id = $user_id"))['count'];
        
        $stats['lost_reports'] = $lost_count;
        $stats['found_reports'] = $found_count;
        $stats['claims_made'] = $claims_count;
        
        $response['success'] = true;
        $response['data'] = $stats;
    }
    elseif(isset($_POST['action']) && $_POST['action'] == 'add_admin') {
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Check if user exists
        $check = "SELECT * FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $check);
        
        if(mysqli_num_rows($result) == 0) {
            $query = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$password', 'admin')";
            
            if(mysqli_query($conn, $query)) {
                $new_id = mysqli_insert_id($conn);
                logActivity($conn, $_SESSION['user_id'], 'ADD_ADMIN', 
                           "Added new admin: $username ($email)");
                
                $response['success'] = true;
                $response['message'] = 'Admin account created successfully';
            } else {
                $response['message'] = 'Failed to create admin: ' . mysqli_error($conn);
            }
        } else {
            $response['message'] = 'Email already exists';
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
else {
    header("Location: ../../Frontend/Admin/manage_accounts.php");
}
exit();
?>