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

if(isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $admin_id = $_SESSION['user_id'];
    
    if($action == 'restore') {
        // Get item details from deleted_items
        $query = "SELECT * FROM deleted_items WHERE id = $id";
        $result = mysqli_query($conn, $query);
        $item = mysqli_fetch_assoc($result);
        
        if($item) {
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Restore to original table
                if($item['report_type'] == 'lost') {
                    $restore_query = "INSERT INTO lost_reports (id, user_id, item_name, category, description, location, date_lost, created_at) 
                                     VALUES ('{$item['original_id']}', '{$item['user_id']}', 
                                            '{$item['item_name']}', '{$item['category']}', 
                                            '{$item['description']}', '{$item['location']}', 
                                            '{$item['date_reported']}', '{$item['deleted_at']}')";
                } else {
                    $restore_query = "INSERT INTO found_reports (id, user_id, item_name, category, description, location, date_found, status, created_at) 
                                     VALUES ('{$item['original_id']}', '{$item['user_id']}', 
                                            '{$item['item_name']}', '{$item['category']}', 
                                            '{$item['description']}', '{$item['location']}', 
                                            '{$item['date_reported']}', 'pending', '{$item['deleted_at']}')";
                }
                
                if(mysqli_query($conn, $restore_query)) {
                    // Delete from deleted_items
                    $delete_query = "DELETE FROM deleted_items WHERE id = $id";
                    mysqli_query($conn, $delete_query);
                    
                    // Log activity
                    logActivity($conn, $admin_id, 'RESTORE_ITEM', 
                               "Restored {$item['report_type']} item ID: {$item['original_id']} - {$item['item_name']}");
                    
                    // Send notification to original owner
                    sendNotification($conn, $item['user_id'],
                        'Item Restored',
                        "Your {$item['report_type']} item '{$item['item_name']}' has been restored from the recycle bin.");
                    
                    mysqli_commit($conn);
                    header("Location: ../../Frontend/Admin/recyclebin.php?msg=restored");
                } else {
                    throw new Exception(mysqli_error($conn));
                }
            } catch (Exception $e) {
                mysqli_rollback($conn);
                header("Location: ../../Frontend/Admin/recyclebin.php?error=" . urlencode($e->getMessage()));
            }
        } else {
            header("Location: ../../Frontend/Admin/recyclebin.php?error=Item not found");
        }
    } 
    elseif($action == 'permanent') {
        // Permanently delete item
        $query = "SELECT * FROM deleted_items WHERE id = $id";
        $result = mysqli_query($conn, $query);
        $item = mysqli_fetch_assoc($result);
        
        if($item) {
            $delete_query = "DELETE FROM deleted_items WHERE id = $id";
            if(mysqli_query($conn, $delete_query)) {
                logActivity($conn, $admin_id, 'PERMANENT_DELETE', 
                           "Permanently deleted {$item['report_type']} item: {$item['item_name']} (Original ID: {$item['original_id']})");
                header("Location: ../../Frontend/Admin/recyclebin.php?msg=permanent_deleted");
            } else {
                header("Location: ../../Frontend/Admin/recyclebin.php?error=" . urlencode(mysqli_error($conn)));
            }
        }
    }
}
elseif(isset($_GET['action']) && $_GET['action'] == 'empty') {
    // Empty entire recycle bin
    $admin_id = $_SESSION['user_id'];
    
    // Get count before deletion
    $count_query = "SELECT COUNT(*) as total FROM deleted_items";
    $result = mysqli_query($conn, $count_query);
    $count = mysqli_fetch_assoc($result)['total'];
    
    $query = "TRUNCATE TABLE deleted_items";
    if(mysqli_query($conn, $query)) {
        logActivity($conn, $admin_id, 'EMPTY_RECYCLE_BIN', 
                   "Emptied recycle bin, permanently deleted $count items");
        header("Location: ../../Frontend/Admin/recyclebin.php?msg=emptied&count=$count");
    } else {
        header("Location: ../../Frontend/Admin/recyclebin.php?error=" . urlencode(mysqli_error($conn)));
    }
}
elseif(isset($_GET['action']) && $_GET['action'] == 'cleanup_old') {
    // Automatically delete items older than 30 days
    $admin_id = $_SESSION['user_id'];
    
    $query = "DELETE FROM deleted_items WHERE deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    if(mysqli_query($conn, $query)) {
        $deleted_count = mysqli_affected_rows($conn);
        logActivity($conn, $admin_id, 'CLEANUP_OLD_ITEMS', 
                   "Automatically deleted $deleted_count items older than 30 days");
        header("Location: ../../Frontend/Admin/recyclebin.php?msg=cleanup&count=$deleted_count");
    } else {
        header("Location: ../../Frontend/Admin/recyclebin.php?error=" . urlencode(mysqli_error($conn)));
    }
}
elseif($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle AJAX requests
    $response = ['success' => false, 'message' => 'Invalid request'];
    
    if(isset($_POST['action']) && $_POST['action'] == 'get_item_details') {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $query = "SELECT * FROM deleted_items WHERE id = $id";
        $result = mysqli_query($conn, $query);
        
        if($item = mysqli_fetch_assoc($result)) {
            $response['success'] = true;
            $response['data'] = $item;
        } else {
            $response['message'] = 'Item not found';
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
else {
    header("Location: ../../Frontend/Admin/recyclebin.php");
}
exit();
?>